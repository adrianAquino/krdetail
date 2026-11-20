<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\Notificacao;
use App\Models\OrdemServico;
use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConcluirTarefaService
{
    /**
     * Conclui uma tarefa, gera o lançamento financeiro idempotente da receita
     * e finaliza a Ordem de Serviço e o Agendamento quando todas as tarefas terminam.
     */
    public function concluir(
        Tarefa|int $tarefa,
        Funcionario|int|null $funcionario = null,
        User|int|null $usuarioResponsavel = null
    ): Tarefa {
        return DB::transaction(function () use ($tarefa, $funcionario, $usuarioResponsavel) {
            $tarefaId = $tarefa instanceof Tarefa ? $tarefa->id : (int) $tarefa;

            $ordemServicoId = $tarefa instanceof Tarefa && $tarefa->ordem_servico_id
                ? (int) $tarefa->ordem_servico_id
                : (int) Tarefa::withoutGlobalScopes()->where('id', $tarefaId)->value('ordem_servico_id');

            if ($ordemServicoId <= 0) {
                throw new \DomainException('Tarefa não encontrada.');
            }

            // Ordem fixa de lock: OrdemServico -> Tarefa -> Agendamento -> LancamentoFinanceiro
            $ordemServico = OrdemServico::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($ordemServicoId);

            $tarefaModel = Tarefa::withoutGlobalScopes()
                ->where('ordem_servico_id', $ordemServico->id)
                ->lockForUpdate()
                ->findOrFail($tarefaId);

            $agendamento = Agendamento::withoutGlobalScopes()
                ->with(['cliente', 'veiculo'])
                ->lockForUpdate()
                ->findOrFail($ordemServico->agendamento_id);

            if (in_array($ordemServico->status, ['cancelada', 'concluida'], true)) {
                throw new \DomainException('Esta Ordem de Serviço já foi concluída ou cancelada.');
            }

            if (in_array($agendamento->status, ['cancelado', 'concluido'], true)) {
                throw new \DomainException('O agendamento vinculado já foi concluído ou cancelado.');
            }

            if ($tarefaModel->status !== 'em_andamento') {
                throw new \DomainException('Apenas tarefas em andamento podem ser concluídas.');
            }

            $empresaOsId = $agendamento->cliente?->empresa_id;

            if ($funcionario !== null) {
                $funcionarioId = $funcionario instanceof Funcionario ? $funcionario->id : (int) $funcionario;
                $funcModel = Funcionario::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->find($funcionarioId);

                if (!$funcModel) {
                    throw new \DomainException('Funcionário informado não foi encontrado.');
                }

                if (!$funcModel->ativo) {
                    throw new \DomainException('Não é possível atribuir um funcionário inativo na conclusão da tarefa.');
                }

                if ($empresaOsId !== null && (int) $funcModel->empresa_id !== (int) $empresaOsId) {
                    throw new \DomainException('O funcionário selecionado não pertence à mesma empresa desta Ordem de Serviço.');
                }

                $tarefaModel->funcionario_id = $funcModel->id;
            }

            $agora = now();

            if (!$tarefaModel->data_inicio) {
                $tarefaModel->data_inicio = $agora;
            }
            $tarefaModel->status = 'concluida';
            $tarefaModel->data_conclusao = $agora;
            $tarefaModel->save();

            // Lançamento financeiro idempotente com lock
            $lancamentoExistente = LancamentoFinanceiro::withoutGlobalScopes()
                ->where('tarefa_id', $tarefaModel->id)
                ->lockForUpdate()
                ->first();

            $agendamentoServico = AgendamentoServico::withoutGlobalScopes()
                ->with('servico')
                ->find($tarefaModel->agendamento_servico_id);

            $valorReceita = round(
                (float) ($agendamentoServico?->valor_servico ?? $agendamentoServico?->servico?->preco_base ?? 0),
                2
            );

            $usuarioId = $usuarioResponsavel instanceof User
                ? $usuarioResponsavel->id
                : ($usuarioResponsavel ? (int) $usuarioResponsavel : Auth::id());

            if (!$lancamentoExistente && $valorReceita > 0) {
                $servicoNome = $agendamentoServico?->servico?->nome ?? 'Serviço de Estética Automotiva';

                LancamentoFinanceiro::create([
                    'empresa_id' => $empresaOsId,
                    'tarefa_id' => $tarefaModel->id,
                    'usuario_id' => $usuarioId,
                    'tipo' => 'receita',
                    'categoria' => 'Serviços',
                    'descricao' => mb_substr("Serviço: {$servicoNome} (OS {$ordemServico->numero})", 0, 255),
                    'valor' => $valorReceita,
                    'data_lancamento' => $agora->toDateString(),
                ]);
            }

            // Verifica com lock se ainda restam tarefas abertas na mesma OS
            $tarefasRestantes = $ordemServico->tarefas()
                ->withoutGlobalScopes()
                ->where('id', '!=', $tarefaModel->id)
                ->where('status', '!=', 'concluida')
                ->lockForUpdate()
                ->count();

            if ($tarefasRestantes === 0) {
                $ordemServico->status = 'concluida';
                $ordemServico->data_fim = $agora;
                $ordemServico->save();

                $agendamento->status = 'concluido';
                $agendamento->data_conclusao = $agora;
                $agendamento->save();

                if ($agendamento->cliente_id) {
                    $veiculoInfo = $agendamento->veiculo
                        ? "{$agendamento->veiculo->marca} {$agendamento->veiculo->modelo} ({$agendamento->veiculo->placa})"
                        : 'seu veículo';

                    Notificacao::create([
                        'cliente_id' => $agendamento->cliente_id,
                        'tipo' => 'os_concluida',
                        'titulo' => "Serviço concluído — OS {$ordemServico->numero}",
                        'mensagem' => "Todos os serviços da {$ordemServico->numero} para {$veiculoInfo} foram finalizados e o veículo está pronto.",
                        'status' => 'pendente',
                        'data_envio' => $agora,
                    ]);
                }
            } else {
                if ($ordemServico->status === 'aberta') {
                    $ordemServico->status = 'em_andamento';
                    $ordemServico->save();
                }
                if (in_array($agendamento->status, ['agendado', 'confirmado'], true)) {
                    $agendamento->status = 'em_andamento';
                    $agendamento->save();
                }
            }

            return $tarefaModel->fresh([
                'ordemServico.agendamento.cliente.pessoa',
                'agendamentoServico.servico',
                'funcionario.user',
                'lancamentoFinanceiro',
            ]);
        });
    }
}
