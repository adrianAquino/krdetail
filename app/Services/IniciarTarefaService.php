<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Funcionario;
use App\Models\OrdemServico;
use App\Models\Tarefa;
use Illuminate\Support\Facades\DB;

class IniciarTarefaService
{
    /**
     * Inicia a execução de uma tarefa pendente, validando bloqueios da OS,
     * agendamento e vínculo do funcionário com a mesma empresa.
     */
    public function iniciar(Tarefa|int $tarefa, Funcionario|int|null $funcionario = null): Tarefa
    {
        return DB::transaction(function () use ($tarefa, $funcionario) {
            $tarefaId = $tarefa instanceof Tarefa ? $tarefa->id : (int) $tarefa;

            $ordemServicoId = $tarefa instanceof Tarefa && $tarefa->ordem_servico_id
                ? (int) $tarefa->ordem_servico_id
                : (int) Tarefa::withoutGlobalScopes()->where('id', $tarefaId)->value('ordem_servico_id');

            if ($ordemServicoId <= 0) {
                throw new \DomainException('Tarefa não encontrada.');
            }

            // Ordem fixa de lock: OrdemServico -> Tarefa -> Agendamento -> Funcionario
            $ordemServico = OrdemServico::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($ordemServicoId);

            $tarefaModel = Tarefa::withoutGlobalScopes()
                ->where('ordem_servico_id', $ordemServico->id)
                ->lockForUpdate()
                ->findOrFail($tarefaId);

            $agendamento = Agendamento::withoutGlobalScopes()
                ->with(['cliente' => fn ($q) => $q->withoutGlobalScopes()])
                ->lockForUpdate()
                ->findOrFail($ordemServico->agendamento_id);

            if (in_array($ordemServico->status, ['cancelada', 'concluida'], true)) {
                throw new \DomainException('Não é possível iniciar tarefas de uma Ordem de Serviço cancelada ou concluída.');
            }

            if (in_array($agendamento->status, ['cancelado', 'concluido'], true)) {
                throw new \DomainException('Não é possível iniciar tarefas de um agendamento cancelado ou concluído.');
            }

            if ($tarefaModel->status !== 'pendente') {
                throw new \DomainException('Apenas tarefas com status pendente podem ser iniciadas.');
            }

            $empresaOsId = $agendamento->empresa_id ?? $agendamento->cliente?->empresa_id;

            if ($funcionario !== null) {
                $funcionarioId = $funcionario instanceof Funcionario ? $funcionario->id : (int) $funcionario;
                $funcModel = Funcionario::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->find($funcionarioId);

                if (!$funcModel) {
                    throw new \DomainException('Funcionário informado não foi encontrado.');
                }

                if (!$funcModel->ativo) {
                    throw new \DomainException('Não é possível iniciar a tarefa com um funcionário inativo.');
                }

                if ($empresaOsId === null || (int) $funcModel->empresa_id !== (int) $empresaOsId) {
                    throw new \DomainException('O funcionário selecionado não pertence à mesma empresa desta Ordem de Serviço.');
                }

                $tarefaModel->funcionario_id = $funcModel->id;
            } elseif ($tarefaModel->funcionario_id) {
                $funcAtual = Funcionario::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->find($tarefaModel->funcionario_id);

                if ($funcAtual && !$funcAtual->ativo) {
                    throw new \DomainException('O funcionário atribuído a esta tarefa está inativo.');
                }

                if ($funcAtual && ($empresaOsId === null || (int) $funcAtual->empresa_id !== (int) $empresaOsId)) {
                    throw new \DomainException('O funcionário atribuído não pertence à mesma empresa desta Ordem de Serviço.');
                }
            } else {
                throw new \DomainException('Atribua um funcionário responsável antes de iniciar a tarefa.');
            }

            $agora = now();

            $tarefaModel->status = 'em_andamento';
            if (!$tarefaModel->data_inicio) {
                $tarefaModel->data_inicio = $agora;
            }
            $tarefaModel->save();

            if ($ordemServico->status === 'aberta') {
                $ordemServico->status = 'em_andamento';
                $ordemServico->save();
            }

            if (in_array($agendamento->status, ['agendado', 'confirmado'], true)) {
                $agendamento->status = 'em_andamento';
                $agendamento->save();
            }

            return $tarefaModel->fresh([
                'ordemServico.agendamento.cliente.pessoa',
                'agendamentoServico.servico',
                'funcionario.user',
            ]);
        });
    }
}
