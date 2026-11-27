<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza o tempo estimado dos serviços existentes para múltiplos de 30 minutos
     * (mínimo 30, máximo 1440), alinhando-os às janelas de 30 min da agenda.
     */
    public function up(): void
    {
        $servicos = DB::table('servicos')
            ->select(['id', 'tempo_estimado_minutos'])
            ->whereNotNull('tempo_estimado_minutos')
            ->get();

        foreach ($servicos as $servico) {
            $tempoAtual = (int) $servico->tempo_estimado_minutos;
            if ($tempoAtual < 30 || ($tempoAtual % 30) !== 0) {
                $tempoNormalizado = min(1440, max(30, (int) (ceil($tempoAtual / 30) * 30)));
                DB::table('servicos')
                    ->where('id', $servico->id)
                    ->update(['tempo_estimado_minutos' => $tempoNormalizado]);
            }
        }
    }

    /**
     * Operação de normalização idempotente sem reversão destrutiva.
     */
    public function down(): void
    {
        // Sem reversão destrutiva para preservar tempos alinhados às janelas.
    }
};
