<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\Tarefa;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ConcluirTarefaService
{
    /**
     * Conclui a execução de uma tarefa, cria o lançamento financeiro automático
     * e verifica se todas as tarefas da Ordem de Serviço foram finalizadas.
     *
     * @param Tarefa|int $tarefa
     * @param Funcionario|int|null $funcionario
     * @param User|int|null $usuarioResponsavel
     * @return Tarefa
     * @throws DomainException
     */
    public function concluir(
        Tarefa|int $tarefa, 
        Funcionario|int|null $funcionario = null, 
        User|int|null $usuarioResponsavel = null
    ): Tarefa {
        $tarefaModel = $tarefa instanceof Tarefa 
            ? $tarefa 
            : Tarefa::with(['ordemServico.tarefas', 'agendamentoServico.servico', 'funcionario.user', 'lancamentoFinanceiro'])->findOrFail($tarefa);

        // 1. Validação de estado da tarefa
        if ($tarefaModel->status === 'concluida') {
            throw new DomainException('A tarefa já foi concluída anteriormente.');
        }

        // 2. Validação do funcionário responsável
        if ($funcionario !== null) {
            $funcionarioModel = $funcionario instanceof Funcionario 
                ? $funcionario 
                : Funcionario::findOrFail($funcionario);
            
            if (!$funcionarioModel->ativo) {
                throw new DomainException('O funcionário responsável está inativo no sistema.');
            }
            $tarefaModel->funcionario_id = $funcionarioModel->id;
        } elseif (!$tarefaModel->funcionario_id) {
            throw new DomainException('A tarefa não possui um funcionário responsável atribuído.');
        }

        // 3. Execução dentro de transação atômica
        return DB::transaction(function () use ($tarefaModel, $usuarioResponsavel) {
            $agora = Carbon::now();

            // A) Marca a tarefa como concluída
            $tarefaModel->status = 'concluida';
            $tarefaModel->data_conclusao = $agora;
            if (!$tarefaModel->data_inicio) {
                $tarefaModel->data_inicio = $agora;
            }
            $tarefaModel->save();

            // B) Lançamento financeiro automático
            // Regra: Uma tarefa pode gerar no máximo UM lançamento financeiro automático (tarefa_id é unique)
            $lancamentoExistente = LancamentoFinanceiro::where('tarefa_id', $tarefaModel->id)->first();

            if (!$lancamentoExistente) {
                // Origem do valor: valor_servico histórico congelado em agendamento_servico
                $valorHistorico = $tarefaModel->agendamentoServico 
                    ? (float) $tarefaModel->agendamentoServico->valor_servico 
                    : 0.00;

                $servicoNome = $tarefaModel->agendamentoServico && $tarefaModel->agendamentoServico->servico 
                    ? $tarefaModel->agendamentoServico->servico->nome 
                    : 'Serviço de Detalhamento';

                $numeroOS = $tarefaModel->ordemServico 
                    ? $tarefaModel->ordemServico->numero 
                    : "OS-{$tarefaModel->ordem_servico_id}";

                $usuarioId = null;
                if ($usuarioResponsavel instanceof User) {
                    $usuarioId = $usuarioResponsavel->id;
                } elseif (is_numeric($usuarioResponsavel)) {
                    $usuarioId = (int) $usuarioResponsavel;
                } else {
                    $usuarioId = Auth::id() ?? $tarefaModel->funcionario?->user_id;
                }

                LancamentoFinanceiro::create([
                    'tarefa_id' => $tarefaModel->id,
                    'usuario_id' => $usuarioId,
                    'tipo' => 'receita',
                    'categoria' => 'Serviços Prestados',
                    'descricao' => "Receita da tarefa: {$servicoNome} ({$numeroOS})",
                    'valor' => $valorHistorico,
                    'data_lancamento' => $agora->toDateString(),
                ]);
            }

            // C) Verificação da Ordem de Serviço
            // Se todas as tarefas da OS estiverem concluídas, conclui a OS e o agendamento
            if ($tarefaModel->ordemServico) {
                $ordemServico = $tarefaModel->ordemServico;
                
                $tarefasPendentes = $ordemServico->tarefas()
                    ->where('id', '!=', $tarefaModel->id)
                    ->where('status', '!=', 'concluida')
                    ->count();

                if ($tarefasPendentes === 0) {
                    $ordemServico->update([
                        'status' => 'concluida',
                        'data_fim' => $agora,
                    ]);

                    if ($ordemServico->agendamento) {
                        $ordemServico->agendamento->update(['status' => 'concluido']);
                    }
                }
            }

            return $tarefaModel->fresh(['lancamentoFinanceiro', 'ordemServico', 'funcionario.user']);
        });
    }
}