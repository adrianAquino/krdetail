<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('agendamentos', 'data_conclusao')) {
            Schema::table('agendamentos', function (Blueprint $table) {
                $table->dateTime('data_conclusao')->nullable()->after('data_fim');
            });
        }

        // Preenche data_conclusao para agendamentos já concluídos a partir da data_fim da OS vinculada
        $agendamentosConcluidos = DB::table('agendamentos')
            ->where('status', 'concluido')
            ->whereNull('data_conclusao')
            ->get(['id', 'updated_at']);

        foreach ($agendamentosConcluidos as $ag) {
            $osDataFim = DB::table('ordem_servicos')
                ->where('agendamento_id', $ag->id)
                ->value('data_fim');

            DB::table('agendamentos')
                ->where('id', $ag->id)
                ->update([
                    'data_conclusao' => $osDataFim ?? $ag->updated_at ?? now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('agendamentos', 'data_conclusao')) {
            Schema::table('agendamentos', function (Blueprint $table) {
                $table->dropColumn('data_conclusao');
            });
        }
    }
};
