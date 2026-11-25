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
        if (Schema::hasTable('agendamentos')) {
            if (! Schema::hasColumn('agendamentos', 'empresa_id')) {
                Schema::table('agendamentos', function (Blueprint $table) {
                    $table->foreignId('empresa_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('empresas')
                        ->nullOnDelete();
                    $table->index('empresa_id', 'agendamentos_empresa_id_index');
                });
            }

            if (! Schema::hasColumn('agendamentos', 'deixar_veiculo')) {
                Schema::table('agendamentos', function (Blueprint $table) {
                    $table->boolean('deixar_veiculo')
                        ->default(false)
                        ->after('data_conclusao');
                });
            }

            // Backfill de empresa_id nos agendamentos existentes a partir de clientes.empresa_id
            $agendamentosSemEmpresa = DB::table('agendamentos')
                ->whereNull('empresa_id')
                ->whereNotNull('cliente_id')
                ->get(['id', 'cliente_id']);

            foreach ($agendamentosSemEmpresa as $ag) {
                $empresaId = DB::table('clientes')
                    ->where('id', $ag->cliente_id)
                    ->value('empresa_id');

                if ($empresaId !== null) {
                    DB::table('agendamentos')
                        ->where('id', $ag->id)
                        ->update(['empresa_id' => $empresaId]);
                }
            }
        }

        if (! Schema::hasTable('cliente_empresa_historicos')) {
            Schema::create('cliente_empresa_historicos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->foreignId('cliente_id')
                    ->nullable()
                    ->constrained('clientes')
                    ->nullOnDelete();
                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->cascadeOnDelete();
                $table->timestamp('vinculado_em')->useCurrent();
                $table->timestamp('desvinculado_em')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'empresa_id'], 'ceh_user_empresa_index');
                $table->index(['cliente_id', 'empresa_id'], 'ceh_cliente_empresa_index');
            });

            // Backfill inicial para todos os clientes atualmente vinculados a alguma estética
            $clientesVinculados = DB::table('clientes')
                ->whereNotNull('empresa_id')
                ->get(['id', 'user_id', 'empresa_id', 'created_at', 'updated_at']);

            foreach ($clientesVinculados as $cli) {
                $agora = now();
                DB::table('cliente_empresa_historicos')->insert([
                    'user_id' => $cli->user_id,
                    'cliente_id' => $cli->id,
                    'empresa_id' => $cli->empresa_id,
                    'vinculado_em' => $cli->created_at ?? $agora,
                    'desvinculado_em' => null,
                    'created_at' => $cli->created_at ?? $agora,
                    'updated_at' => $cli->updated_at ?? $agora,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_empresa_historicos');

        if (Schema::hasTable('agendamentos')) {
            if (Schema::hasColumn('agendamentos', 'deixar_veiculo')) {
                Schema::table('agendamentos', function (Blueprint $table) {
                    $table->dropColumn('deixar_veiculo');
                });
            }

            if (Schema::hasColumn('agendamentos', 'empresa_id')) {
                Schema::table('agendamentos', function (Blueprint $table) {
                    $table->dropForeign(['empresa_id']);
                });

                if (Schema::hasIndex('agendamentos', 'agendamentos_empresa_id_index')) {
                    Schema::table('agendamentos', function (Blueprint $table) {
                        $table->dropIndex('agendamentos_empresa_id_index');
                    });
                }

                Schema::table('agendamentos', function (Blueprint $table) {
                    $table->dropColumn('empresa_id');
                });
            }
        }
    }
};
