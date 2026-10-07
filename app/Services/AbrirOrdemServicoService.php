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
     * @param Agendamento|int $agendamento
     * @param string|null $observacoes
     * @return OrdemServico
     * @throws DomainException
     */
    public function abrir(Agendamento|int $agendamento, ?string $observacoes = null): OrdemServico
    {
        $agendamentoModel = $agendamento instanceof Agendamento 
            ? $agendamento 
            : Agendamento::with('agendamentosServicos')->findOrFail($agendamento);

        // 1. Verifica se o agendamento pode originar uma OS
        if ($agendamentoModel->status === 'cancelado') {
            throw new DomainException('Não é possível abrir uma Ordem de Serviço para um agendamento cancelado.');
        }

        // 2. Impede a criação de uma segunda OS para o mesmo agendamento
        if ($agendamentoModel->ordemServico()->exists()) {
            throw new DomainException('Este agendamento já possui uma Ordem de Serviço associada.');
        }

        // 3. Garante que o agendamento possui serviços contratados
        $servicosContratados = $agendamentoModel->agendamentosServicos;
        if ($servicosContratados->isEmpty()) {
            throw new DomainException('O agendamento não possui serviços contratados para gerar tarefas.');
        }

        // 4. Executa a criação da OS e tarefas dentro de uma transação atômica
        return DB::transaction(function () use ($agendamentoModel, $servicosContratados, $observacoes) {
            // Gera número formatado e único para a OS
            $numeroBase = 'OS-' . str_pad((string) $agendamentoModel->id, 5, '0', STR_PAD_LEFT);
            $numeroOS = $numeroBase;
            $contador = 1;
            while (OrdemServico::where('numero', $numeroOS)->exists()) {
                $numeroOS = $numeroBase . '-' . $contador++;
            }

            // Cria a Ordem de Serviço
            $ordemServico = OrdemServico::create([
                'agendamento_id' => $agendamentoModel->id,
                'numero' => $numeroOS,
                'data_inicio' => Carbon::now(),
                'status' => 'aberta',
                'observacoes' => $observacoes ?? $agendamentoModel->observacoes,
            ]);

            // Atualiza o agendamento para confirmado caso estivesse apenas agendado
            if ($agendamentoModel->status === 'agendado') {
                $agendamentoModel->update(['status' => 'confirmado']);
            }

            // Cria automaticamente uma tarefa para cada serviço contratado
            // mantendo o vínculo com agendamento_servico para preservar valor e tempo históricos
            foreach ($servicosContratados as $agendamentoServico) {
                // Impede que o mesmo agendamento_servico gere mais de uma tarefa
                if (!Tarefa::where('agendamento_servico_id', $agendamentoServico->id)->exists()) {
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