<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Models\Tarefa;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class IniciarTarefaService
{
    /**
     * Inicia a execução de uma tarefa por um colaborador.
     *
     * @param Tarefa|int $tarefa
     * @param Funcionario|int|null $funcionario
     * @return Tarefa
     * @throws DomainException
     */
    public function iniciar(Tarefa|int $tarefa, Funcionario|int|null $funcionario = null): Tarefa
    {
        $tarefaModel = $tarefa instanceof Tarefa 
            ? $tarefa 
            : Tarefa::with(['ordemServico.agendamento', 'funcionario'])->findOrFail($tarefa);

        // 1. Verifica se a tarefa já está concluída ou em andamento
        if ($tarefaModel->status === 'concluida') {
            throw new DomainException('A tarefa já foi concluída e não pode ser iniciada novamente.');
        }

        if ($tarefaModel->status === 'em_andamento') {
            throw new DomainException('A tarefa já está em andamento.');
        }

        // 2. Verifica se a Ordem de Serviço pai permite execução
        if ($tarefaModel->ordemServico) {
            if ($tarefaModel->ordemServico->status === 'cancelada') {
                throw new DomainException('Não é possível iniciar uma tarefa pertencente a uma Ordem de Serviço cancelada.');
            }
            if ($tarefaModel->ordemServico->status === 'concluida') {
                throw new DomainException('Não é possível iniciar uma tarefa pertencente a uma Ordem de Serviço já concluída.');
            }
        }

        // 3. Valida e obtém o funcionário responsável
        $funcionarioModel = null;
        if ($funcionario !== null) {
            $funcionarioModel = $funcionario instanceof Funcionario 
                ? $funcionario 
                : Funcionario::findOrFail($funcionario);
        } elseif ($tarefaModel->funcionario_id) {
            $funcionarioModel = $tarefaModel->funcionario;
        }

        if (!$funcionarioModel) {
            throw new DomainException('É necessário atribuir um funcionário responsável para iniciar a tarefa.');
        }

        if (!$funcionarioModel->ativo) {
            throw new DomainException('O funcionário responsável está inativo no sistema.');
        }

        // 4. Executa as alterações de estado dentro de transação
        return DB::transaction(function () use ($tarefaModel, $funcionarioModel) {
            $tarefaModel->update([
                'funcionario_id' => $funcionarioModel->id,
                'status' => 'em_andamento',
                'data_inicio' => Carbon::now(),
            ]);

            // Se a Ordem de Serviço pai estava aberta, transiciona para em_andamento
            if ($tarefaModel->ordemServico && $tarefaModel->ordemServico->status === 'aberta') {
                $tarefaModel->ordemServico->update(['status' => 'em_andamento']);
            }

            // Se o agendamento pai ainda não estava em andamento, atualiza para consistência
            if ($tarefaModel->ordemServico && $tarefaModel->ordemServico->agendamento) {
                $agendamento = $tarefaModel->ordemServico->agendamento;
                if (!in_array($agendamento->status, ['em_andamento', 'concluido', 'cancelado'])) {
                    $agendamento->update(['status' => 'em_andamento']);
                }
            }

            return $tarefaModel->fresh(['funcionario.user', 'ordemServico', 'agendamentoServico.servico']);
        });
    }
}