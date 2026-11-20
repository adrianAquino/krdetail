<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\OrdemServico;
use App\Models\Tarefa;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class AbrirOrdemServicoService
{
    /**
     * Transforma um Agendamento em uma Ordem de Serviço com tarefas automáticas.
     *
     * @throws DomainException
     */
    public function abrir(Agendamento|int $agendamento, ?string $observacoes = null): OrdemServico
    {
        $agendamentoId = $agendamento instanceof Agendamento ? $agendamento->id : $agendamento;

        return DB::transaction(function () use ($agendamentoId, $observacoes) {
            $agendamentoModel = Agendamento::withoutGlobalScopes()
                ->lockForUpdate()
                ->with(['agendamentosServicos', 'ordemServico'])
                ->findOrFail($agendamentoId);

            if ($agendamentoModel->status === 'cancelado') {
                throw new DomainException('Não é possível abrir uma Ordem de Serviço para um agendamento cancelado.');
            }

            if ($agendamentoModel->status === 'concluido') {
                throw new DomainException('Não é possível abrir uma Ordem de Serviço para um agendamento já concluído.');
            }

            if ($agendamentoModel->ordemServico()->withoutGlobalScopes()->exists()) {
                throw new DomainException('Este agendamento já possui uma Ordem de Serviço associada.');
            }

            $servicosContratados = $agendamentoModel->agendamentosServicos;
            if ($servicosContratados->isEmpty()) {
                throw new DomainException('O agendamento não possui serviços contratados para gerar tarefas.');
            }

            $numeroBase = 'OS-' . str_pad((string) $agendamentoModel->id, 5, '0', STR_PAD_LEFT);
            $numeroOS = $numeroBase;
            $contador = 1;
            while (OrdemServico::withoutGlobalScopes()->where('numero', $numeroOS)->exists()) {
                $numeroOS = $numeroBase . '-' . $contador++;
            }

            $ordemServico = OrdemServico::create([
                'agendamento_id' => $agendamentoModel->id,
                'numero' => $numeroOS,
                'data_inicio' => Carbon::now(),
                'data_fim' => null,
                'status' => 'aberta',
                'observacoes' => $observacoes ?? $agendamentoModel->observacoes,
            ]);

            $dadosAgendamento = ['data_conclusao' => null];
            if ($agendamentoModel->status === 'agendado') {
                $dadosAgendamento['status'] = 'confirmado';
            }
            $agendamentoModel->update($dadosAgendamento);

            foreach ($servicosContratados as $agendamentoServico) {
                if ((int) $agendamentoServico->agendamento_id !== (int) $ordemServico->agendamento_id) {
                    throw new DomainException(
                        'Inconsistência referencial: o serviço contratado não pertence ao mesmo agendamento da Ordem de Serviço.'
                    );
                }

                if (! Tarefa::withoutGlobalScopes()->where('agendamento_servico_id', $agendamentoServico->id)->exists()) {
                    Tarefa::create([
                        'ordem_servico_id' => $ordemServico->id,
                        'agendamento_servico_id' => $agendamentoServico->id,
                        'funcionario_id' => null,
                        'status' => 'pendente',
                        'data_inicio' => null,
                        'data_conclusao' => null,
                    ]);
                }
            }

            return $ordemServico->load(['tarefas.agendamentoServico.servico', 'agendamento.cliente', 'agendamento.veiculo']);
        });
    }
}
