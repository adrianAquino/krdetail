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
        // 1. Adiciona empresa_id em lancamentos_financeiros e faz backfill do histórico
        if (Schema::hasTable('lancamentos_financeiros') && !Schema::hasColumn('lancamentos_financeiros', 'empresa_id')) {
            Schema::table('lancamentos_financeiros', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();
                $table->index('empresa_id', 'lancamentos_financeiros_empresa_id_index');
            });
        }

        if (Schema::hasTable('lancamentos_financeiros') && Schema::hasColumn('lancamentos_financeiros', 'empresa_id')) {
            $lancamentos = DB::table('lancamentos_financeiros')
                ->whereNull('empresa_id')
                ->get(['id', 'tarefa_id', 'usuario_id']);

            foreach ($lancamentos as $lancamento) {
                $empresaId = null;

                if (!empty($lancamento->tarefa_id)) {
                    $empresaId = DB::table('tarefas')
                        ->join('ordem_servicos', 'tarefas.ordem_servico_id', '=', 'ordem_servicos.id')
                        ->join('agendamentos', 'ordem_servicos.agendamento_id', '=', 'agendamentos.id')
                        ->join('clientes', 'agendamentos.cliente_id', '=', 'clientes.id')
                        ->where('tarefas.id', $lancamento->tarefa_id)
                        ->value('clientes.empresa_id');
                }

                if ($empresaId === null && !empty($lancamento->usuario_id)) {
                    $empresaId = DB::table('users')
                        ->where('id', $lancamento->usuario_id)
                        ->value('empresa_id');
                }

                if ($empresaId !== null) {
                    DB::table('lancamentos_financeiros')
                        ->where('id', $lancamento->id)
                        ->update(['empresa_id' => $empresaId]);
                }
            }
        }

        // 2. Amplia tamanhos em drivers não-SQLite (MySQL/MariaDB/Postgres)
        if (DB::getDriverName() !== 'sqlite') {
            if (Schema::hasTable('pessoas')) {
                Schema::table('pessoas', function (Blueprint $table) {
                    $table->string('nome', 255)->change();
                    $table->string('email', 255)->nullable()->change();
                    $table->string('logradouro', 255)->nullable()->change();
                    $table->string('complemento', 255)->nullable()->change();
                    $table->string('bairro', 255)->nullable()->change();
                    $table->string('cidade', 255)->nullable()->change();
                });
            }

            if (Schema::hasTable('tarefa_produto')) {
                Schema::table('tarefa_produto', function (Blueprint $table) {
                    $table->decimal('custo_unitario', 16, 6)->nullable()->change();
                });
            }
        }

        // 3. Índices de desempenho para dashboard, relatórios e sweep-line
        if (Schema::hasTable('tarefas') && !Schema::hasIndex('tarefas', 'idx_tarefas_status_data_conclusao')) {
            Schema::table('tarefas', function (Blueprint $table) {
                $table->index(['status', 'data_conclusao'], 'idx_tarefas_status_data_conclusao');
            });
        }

        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'email') && !Schema::hasIndex('clientes', 'idx_clientes_email')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->index('email', 'idx_clientes_email');
            });
        }

        if (Schema::hasTable('tarefa_produto') && !Schema::hasIndex('tarefa_produto', 'idx_tarefa_produto_created_at')) {
            Schema::table('tarefa_produto', function (Blueprint $table) {
                $table->index('created_at', 'idx_tarefa_produto_created_at');
            });
        }

        if (Schema::hasTable('ordem_servicos') && !Schema::hasIndex('ordem_servicos', 'idx_ordem_servicos_created_at')) {
            Schema::table('ordem_servicos', function (Blueprint $table) {
                $table->index('created_at', 'idx_ordem_servicos_created_at');
            });
        }

        if (Schema::hasTable('movimentacoes_estoque') && !Schema::hasIndex('movimentacoes_estoque', 'idx_movimentacoes_estoque_created_at')) {
            Schema::table('movimentacoes_estoque', function (Blueprint $table) {
                $table->index('created_at', 'idx_movimentacoes_estoque_created_at');
            });
        }

        if (Schema::hasTable('agendamentos') && !Schema::hasIndex('agendamentos', 'idx_agendamentos_veiculo_status_inicio')) {
            Schema::table('agendamentos', function (Blueprint $table) {
                $table->index(['veiculo_id', 'status', 'data_inicio'], 'idx_agendamentos_veiculo_status_inicio');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('agendamentos') && Schema::hasIndex('agendamentos', 'idx_agendamentos_veiculo_status_inicio')) {
            Schema::table('agendamentos', function (Blueprint $table) {
                $table->dropIndex('idx_agendamentos_veiculo_status_inicio');
            });
        }

        if (Schema::hasTable('movimentacoes_estoque') && Schema::hasIndex('movimentacoes_estoque', 'idx_movimentacoes_estoque_created_at')) {
            Schema::table('movimentacoes_estoque', function (Blueprint $table) {
                $table->dropIndex('idx_movimentacoes_estoque_created_at');
            });
        }

        if (Schema::hasTable('ordem_servicos') && Schema::hasIndex('ordem_servicos', 'idx_ordem_servicos_created_at')) {
            Schema::table('ordem_servicos', function (Blueprint $table) {
                $table->dropIndex('idx_ordem_servicos_created_at');
            });
        }

        if (Schema::hasTable('tarefa_produto') && Schema::hasIndex('tarefa_produto', 'idx_tarefa_produto_created_at')) {
            Schema::table('tarefa_produto', function (Blueprint $table) {
                $table->dropIndex('idx_tarefa_produto_created_at');
            });
        }

        if (Schema::hasTable('clientes') && Schema::hasIndex('clientes', 'idx_clientes_email')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropIndex('idx_clientes_email');
            });
        }

        if (Schema::hasTable('tarefas') && Schema::hasIndex('tarefas', 'idx_tarefas_status_data_conclusao')) {
            Schema::table('tarefas', function (Blueprint $table) {
                $table->dropIndex('idx_tarefas_status_data_conclusao');
            });
        }

        if (Schema::hasTable('lancamentos_financeiros') && Schema::hasColumn('lancamentos_financeiros', 'empresa_id')) {
            if (Schema::hasIndex('lancamentos_financeiros', 'lancamentos_financeiros_empresa_id_index')) {
                Schema::table('lancamentos_financeiros', function (Blueprint $table) {
                    $table->dropIndex('lancamentos_financeiros_empresa_id_index');
                });
            }

            Schema::table('lancamentos_financeiros', function (Blueprint $table) {
                $table->dropConstrainedForeignId('empresa_id');
            });
        }
    }
};
