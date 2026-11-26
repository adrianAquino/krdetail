<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['servicos', 'produtos'] as $tabela) {
            $chavesExistentes = [];

            $registros = DB::table($tabela)
                ->select('id', 'empresa_id', 'nome')
                ->orderBy('id')
                ->get();

            foreach ($registros as $row) {
                $nomeBase = preg_replace('/\s+/', ' ', trim((string) $row->nome)) ?? '';
                if ($nomeBase === '') {
                    $nomeBase = 'Registro';
                }

                $empresaChave = $row->empresa_id === null ? 'null' : (string) $row->empresa_id;
                $candidato = mb_substr($nomeBase, 0, 100, 'UTF-8');
                $contador = 2;

                while (isset($chavesExistentes[$empresaChave . '|' . mb_strtolower($candidato, 'UTF-8')])) {
                    $sufixo = " ({$contador})";
                    $limiteBase = max(1, 100 - mb_strlen($sufixo, 'UTF-8'));
                    $candidato = mb_substr($nomeBase, 0, $limiteBase, 'UTF-8') . $sufixo;
                    $contador++;
                }

                $chavesExistentes[$empresaChave . '|' . mb_strtolower($candidato, 'UTF-8')] = true;

                if ($candidato !== (string) $row->nome) {
                    DB::table($tabela)
                        ->where('id', $row->id)
                        ->update(['nome' => $candidato]);
                }
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->unique(['empresa_id', 'nome'], "{$tabela}_empresa_nome_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (['servicos', 'produtos'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->dropUnique("{$tabela}_empresa_nome_unique");
            });
        }
    }
};
