<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Atende à Seção 9.3 (c) e 9.6 da revisão de modelagem:
     * - Cria a tabela centralizada `pessoas` para evitar redundância e inconsistência de dados
     *   cadastrais (nome, cpf_cnpj, telefone, email, endereço) entre `users` e `clientes`.
     * - Adiciona `pessoa_id` em `users` e `clientes` e sincroniza os registros já existentes.
     * - Adiciona `tarefa_produto_id` em `movimentacoes_estoque` para rastreabilidade direta do consumo em tarefas.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pessoas')) {
            Schema::create('pessoas', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 100);
                $table->string('cpf_cnpj', 20)->nullable()->unique();
                $table->string('telefone', 20)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('cep', 10)->nullable();
                $table->string('logradouro', 100)->nullable();
                $table->string('numero', 20)->nullable();
                $table->string('complemento', 100)->nullable();
                $table->string('bairro', 100)->nullable();
                $table->string('cidade', 100)->nullable();
                $table->string('estado', 2)->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index('email');
                $table->index('telefone');
            });
        }

        if (!Schema::hasColumn('users', 'pessoa_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('pessoa_id')
                    ->nullable()
                    ->after('id')
                    ->unique()
                    ->constrained('pessoas')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('clientes', 'pessoa_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->foreignId('pessoa_id')
                    ->nullable()
                    ->after('id')
                    ->unique()
                    ->constrained('pessoas')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('movimentacoes_estoque') && !Schema::hasColumn('movimentacoes_estoque', 'tarefa_produto_id')) {
            Schema::table('movimentacoes_estoque', function (Blueprint $table) {
                $table->foreignId('tarefa_produto_id')
                    ->nullable()
                    ->after('produto_id')
                    ->constrained('tarefa_produto')
                    ->nullOnDelete();
            });
        }

        // Migra dados existentes de clientes e users para a tabela pessoas
        $clientes = DB::table('clientes')->whereNull('pessoa_id')->get();
        foreach ($clientes as $cliente) {
            $pessoaId = DB::table('pessoas')->insertGetId([
                'nome' => $cliente->nome,
                'cpf_cnpj' => $cliente->cpf_cnpj,
                'telefone' => $cliente->telefone,
                'email' => $cliente->email,
                'cep' => $cliente->cep,
                'logradouro' => $cliente->logradouro,
                'numero' => $cliente->numero,
                'complemento' => $cliente->complemento,
                'bairro' => $cliente->bairro,
                'cidade' => $cliente->cidade,
                'estado' => $cliente->estado,
                'created_at' => $cliente->created_at ?? now(),
                'updated_at' => $cliente->updated_at ?? now(),
            ]);

            DB::table('clientes')->where('id', $cliente->id)->update(['pessoa_id' => $pessoaId]);

            if (!empty($cliente->user_id)) {
                DB::table('users')->where('id', $cliente->user_id)->update(['pessoa_id' => $pessoaId]);
            }
        }

        // Cria registros em pessoas para usuários (ex: administradores e funcionários) ainda sem pessoa_id
        $usersSemPessoa = DB::table('users')->whereNull('pessoa_id')->get();
        foreach ($usersSemPessoa as $user) {
            $pessoaId = DB::table('pessoas')->insertGetId([
                'nome' => $user->name,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'created_at' => $user->created_at ?? now(),
                'updated_at' => $user->updated_at ?? now(),
            ]);

            DB::table('users')->where('id', $user->id)->update(['pessoa_id' => $pessoaId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('movimentacoes_estoque') && Schema::hasColumn('movimentacoes_estoque', 'tarefa_produto_id')) {
            Schema::table('movimentacoes_estoque', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tarefa_produto_id');
            });
        }

        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'pessoa_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pessoa_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'pessoa_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pessoa_id');
            });
        }

        Schema::dropIfExists('pessoas');
    }
};
