<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Atualiza o ENUM tipo_usuario na tabela users para incluir 'dono' (apenas MySQL)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `tipo_usuario` ENUM('administrador', 'dono', 'funcionario', 'cliente') NOT NULL DEFAULT 'cliente'");
        }

        // 2. Cria a tabela empresas com todos os campos de Dados da Empresa
        if (!Schema::hasTable('empresas')) {
            Schema::create('empresas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dono_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('nome_fantasia', 100);
                $table->string('razao_social', 120);
                $table->string('cnpj', 20)->nullable()->unique();
                $table->string('inscricao_estadual', 20)->nullable();
                $table->string('telefone', 20)->nullable();
                $table->string('whatsapp', 20);
                $table->string('email', 100);
                $table->string('cep', 10)->nullable();
                $table->string('logradouro', 100)->nullable();
                $table->string('numero', 15)->nullable();
                $table->string('complemento', 100)->nullable();
                $table->string('bairro', 100)->nullable();
                $table->string('cidade', 100)->nullable();
                $table->string('uf', 2)->nullable();
                $table->string('horario_atendimento', 100)->nullable();
                $table->boolean('ativo')->default(true);
                $table->softDeletes();
                $table->timestamps();

                $table->index('dono_id');
                $table->index('ativo');
            });
        }

        // 3. Adiciona empresa_id em users, clientes, funcionarios, servicos e produtos
        if (!Schema::hasColumn('users', 'empresa_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('pessoa_id')
                    ->constrained('empresas')
                    ->nullOnDelete();
                $table->index('empresa_id');
            });
        }

        if (!Schema::hasColumn('clientes', 'empresa_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('pessoa_id')
                    ->constrained('empresas')
                    ->nullOnDelete();
                $table->index('empresa_id');
            });
        }

        if (!Schema::hasColumn('funcionarios', 'empresa_id')) {
            Schema::table('funcionarios', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();
                $table->index('empresa_id');
            });
        }

        if (!Schema::hasColumn('servicos', 'empresa_id')) {
            Schema::table('servicos', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();
                $table->index('empresa_id');
            });
        }

        if (!Schema::hasColumn('produtos', 'empresa_id')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();
                $table->index('empresa_id');
            });
        }

        // 4. Localiza (ou cria) Rafael Kraus por e-mail exato e altera seu papel para 'dono'
        $rafael = DB::table('users')
            ->where('email', 'rafaelkraus@krdetail.com')
            ->first();

        if ($rafael) {
            DB::table('users')
                ->where('id', $rafael->id)
                ->update([
                    'tipo_usuario' => 'dono',
                    'ativo' => true,
                    'updated_at' => now(),
                ]);
            $rafaelId = $rafael->id;
        } else {
            $pessoaId = DB::table('pessoas')->insertGetId([
                'nome' => 'Rafael Kraus',
                'email' => 'rafaelkraus@krdetail.com',
                'telefone' => '(11) 98765-4321',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rafaelId = DB::table('users')->insertGetId([
                'pessoa_id' => $pessoaId,
                'name' => 'Rafael Kraus',
                'email' => 'rafaelkraus@krdetail.com',
                'password' => Hash::make('12345678'),
                'telefone' => '(11) 98765-4321',
                'tipo_usuario' => 'dono',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Insere a empresa "Kraus Detail" no banco de dados vinculada ao dono Rafael Kraus
        $empresaExistente = DB::table('empresas')
            ->where('nome_fantasia', 'like', '%Kraus Detail%')
            ->orWhere('cnpj', '48.192.304/0001-89')
            ->first();

        if ($empresaExistente) {
            $empresaId = $empresaExistente->id;
            DB::table('empresas')
                ->where('id', $empresaId)
                ->update([
                    'dono_id' => $rafaelId,
                    'updated_at' => now(),
                ]);
        } else {
            $empresaId = DB::table('empresas')->insertGetId([
                'dono_id' => $rafaelId,
                'nome_fantasia' => 'Kraus Detail',
                'razao_social' => 'Kraus Estética Automotiva Especializada LTDA',
                'cnpj' => '48.192.304/0001-89',
                'inscricao_estadual' => 'ISENTO',
                'telefone' => '(11) 3214-5500',
                'whatsapp' => '(11) 98765-4321',
                'email' => 'contato@krausdetail.com.br',
                'cep' => '04538-133',
                'logradouro' => 'Avenida dos Bandeirantes',
                'numero' => '2500',
                'complemento' => 'Galpão B',
                'bairro' => 'Vila Olímpia',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'horario_atendimento' => 'Segunda a Sábado, 08:00 às 18:00',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 6. Vincula Rafael Kraus, funcionários, clientes, serviços e produtos à empresa "Kraus Detail"
        DB::table('users')
            ->whereNull('empresa_id')
            ->where('tipo_usuario', '!=', 'administrador')
            ->update(['empresa_id' => $empresaId]);

        DB::table('clientes')
            ->whereNull('empresa_id')
            ->update(['empresa_id' => $empresaId]);

        DB::table('funcionarios')
            ->whereNull('empresa_id')
            ->update(['empresa_id' => $empresaId]);

        DB::table('servicos')
            ->whereNull('empresa_id')
            ->update(['empresa_id' => $empresaId]);

        DB::table('produtos')
            ->whereNull('empresa_id')
            ->update(['empresa_id' => $empresaId]);

        // 7. Garante que Rafael Kraus (dono) também possua registro em `funcionarios` para prestar serviços
        $funcRafael = DB::table('funcionarios')->where('user_id', $rafaelId)->first();
        if ($funcRafael) {
            DB::table('funcionarios')
                ->where('id', $funcRafael->id)
                ->update([
                    'empresa_id' => $empresaId,
                    'cargo' => 'Proprietário / Especialista',
                    'ativo' => true,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('funcionarios')->insert([
                'empresa_id' => $empresaId,
                'user_id' => $rafaelId,
                'cargo' => 'Proprietário / Especialista',
                'ativo' => true,
                'observacoes' => 'Dono da empresa e especialista habilitado para execução de serviços.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['produtos', 'servicos', 'funcionarios', 'clientes', 'users'] as $tabela) {
            if (Schema::hasColumn($tabela, 'empresa_id')) {
                if (Schema::hasIndex($tabela, "{$tabela}_empresa_id_index")) {
                    Schema::table($tabela, function (Blueprint $table) {
                        $table->dropIndex(['empresa_id']);
                    });
                }

                Schema::table($tabela, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('empresa_id');
                });
            }
        }

        Schema::dropIfExists('empresas');

        DB::table('users')->where('tipo_usuario', 'dono')->update(['tipo_usuario' => 'funcionario']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `tipo_usuario` ENUM('administrador', 'funcionario', 'cliente') NOT NULL DEFAULT 'cliente'");
        }
    }
};
