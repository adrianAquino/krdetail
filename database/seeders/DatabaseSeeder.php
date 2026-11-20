<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $pessoaAdmin = Pessoa::updateOrCreate(
            ['email' => 'admin@krdetail.com'],
            [
                'nome' => 'Administrador',
                'telefone' => '(11) 99999-9999',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@krdetail.com'],
            [
                'pessoa_id' => $pessoaAdmin->id,
                'name' => 'Administrador',
                'password' => Hash::make('12345678'),
                'telefone' => '(11) 99999-9999',
                'tipo_usuario' => 'administrador',
                'ativo' => true,
            ]
        );

        $pessoaDono = Pessoa::updateOrCreate(
            ['email' => 'rafaelkraus@krdetail.com'],
            [
                'nome' => 'Rafael Kraus',
                'telefone' => '(11) 98765-4321',
            ]
        );

        $dono = User::updateOrCreate(
            ['email' => 'rafaelkraus@krdetail.com'],
            [
                'pessoa_id' => $pessoaDono->id,
                'name' => 'Rafael Kraus',
                'password' => Hash::make('12345678'),
                'telefone' => '(11) 98765-4321',
                'tipo_usuario' => 'dono',
                'ativo' => true,
            ]
        );

        $empresa = Empresa::updateOrCreate(
            ['cnpj' => '48.192.304/0001-89'],
            [
                'dono_id' => $dono->id,
                'nome_fantasia' => 'Kraus Detail',
                'razao_social' => 'Kraus Estética Automotiva Especializada LTDA',
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
            ]
        );

        $dono->update(['empresa_id' => $empresa->id]);

        Funcionario::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $dono->id],
            [
                'empresa_id' => $empresa->id,
                'cargo' => 'Proprietário / Especialista',
                'ativo' => true,
            ]
        );
    }
}
