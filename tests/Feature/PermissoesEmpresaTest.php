<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissoesEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualizacao_de_perfil_do_cliente_sincroniza_pessoas_users_e_clientes(): void
    {
        $empresa = Empresa::first() ?? Empresa::create([
            'nome_fantasia' => 'Kraus Detail Matriz',
            'razao_social' => 'Kraus Detail Matriz LTDA',
            'whatsapp' => '(11) 99999-1111',
            'email' => 'matriz@krausdetail.test',
            'ativo' => true,
        ]);

        $userCliente = User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Cliente Inicial',
            'email' => 'cliente.sync@krdetail.test',
            'password' => '12345678',
            'telefone' => '(11) 98888-0000',
            'tipo_usuario' => 'cliente',
            'ativo' => true,
        ]);

        $cliente = $userCliente->ensureClienteProfile();

        $this->actingAs($userCliente)->put(route('configuracoes.perfil.update'), [
            'name' => 'Cliente Sincronizado 3FN',
            'email' => 'cliente.novo@krdetail.test',
            'telefone' => '(11) 97777-8888',
            'cpf_cnpj' => '529.982.247-25',
            'cep' => '04538-133',
            'logradouro' => 'Avenida Paulista',
            'numero' => '1000',
            'complemento' => 'Conjunto 10',
            'bairro' => 'Bela Vista',
            'cidade' => 'São Paulo',
            'estado' => 'sp',
        ])->assertRedirect(route('configuracoes.index', ['tab' => 'perfil']))
          ->assertSessionHas('success');

        $userCliente->refresh();
        $cliente->refresh();
        $pessoa = Pessoa::find($userCliente->pessoa_id);

        $this->assertNotNull($pessoa);
        $this->assertSame($pessoa->id, $cliente->pessoa_id);
        $this->assertSame('Cliente Sincronizado 3FN', $userCliente->name);
        $this->assertSame('Cliente Sincronizado 3FN', $pessoa->nome);
        $this->assertSame('Cliente Sincronizado 3FN', $cliente->nome);
        $this->assertSame('cliente.novo@krdetail.test', $cliente->email);
        $this->assertSame('529.982.247-25', $pessoa->cpf_cnpj);
        $this->assertSame('529.982.247-25', $cliente->cpf_cnpj);
        $this->assertSame('SP', $pessoa->estado);
        $this->assertSame('Avenida Paulista', $cliente->logradouro);
    }

    public function test_restricoes_de_acesso_para_cliente_e_funcionario(): void
    {
        $empresa = Empresa::first() ?? Empresa::create([
            'nome_fantasia' => 'Kraus Detail RBAC',
            'razao_social' => 'Kraus Detail RBAC LTDA',
            'whatsapp' => '(11) 99999-2222',
            'email' => 'rbac@krausdetail.test',
            'ativo' => true,
        ]);

        $userCliente = User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Cliente Restrito',
            'email' => 'cliente.rbac@krdetail.test',
            'password' => '12345678',
            'telefone' => '(11) 91111-2222',
            'tipo_usuario' => 'cliente',
            'ativo' => true,
        ]);
        $userCliente->ensureClienteProfile();

        // Cliente bloqueado de rotas da equipe
        $this->actingAs($userCliente)->get(route('clientes.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($userCliente)->get(route('ordens-servico.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        // Cliente consegue acessar suas configurações e editar perfil
        $this->actingAs($userCliente)->get(route('configuracoes.index'))->assertOk();

        // Funcionário acessa configurações, mas não edita empresa nem cria usuários
        $userFuncionario = User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Funcionário Padrão',
            'email' => 'func.rbac@krdetail.test',
            'password' => '12345678',
            'telefone' => '(11) 92222-3333',
            'tipo_usuario' => 'funcionario',
            'ativo' => true,
        ]);

        $this->actingAs($userFuncionario)->get(route('configuracoes.index'))->assertOk();

        $nomeOriginal = $empresa->nome_fantasia;
        $this->actingAs($userFuncionario)->put(route('configuracoes.empresas.update', $empresa), [
            'nome_fantasia' => 'Nome Alterado Indevidamente',
            'razao_social' => $empresa->razao_social,
            'whatsapp' => $empresa->whatsapp,
            'email' => $empresa->email,
        ])->assertSessionHas('error');

        $this->assertSame($nomeOriginal, $empresa->fresh()->nome_fantasia);

        $this->actingAs($userFuncionario)->post(route('configuracoes.usuarios.store'), [
            'name' => 'Novo Usuario Indevido',
            'email' => 'indevido@krdetail.test',
            'tipo_usuario' => 'funcionario',
            'password' => '12345678',
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'indevido@krdetail.test']);
    }

    public function test_isolamento_por_empresa_do_dono_e_integridade_na_exclusao(): void
    {
        $empresaA = Empresa::create([
            'nome_fantasia' => 'Estética Alfa',
            'razao_social' => 'Estética Alfa LTDA',
            'cnpj' => '12.345.678/0001-95',
            'whatsapp' => '(11) 91111-1111',
            'email' => 'alfa@estetica.test',
            'ativo' => true,
        ]);

        $empresaB = Empresa::create([
            'nome_fantasia' => 'Estética Beta',
            'razao_social' => 'Estética Beta LTDA',
            'cnpj' => '98.765.432/0001-10',
            'whatsapp' => '(11) 92222-2222',
            'email' => 'beta@estetica.test',
            'ativo' => true,
        ]);

        $donoA = User::create([
            'empresa_id' => $empresaA->id,
            'name' => 'Dono Alfa',
            'email' => 'dono.alfa@estetica.test',
            'password' => '12345678',
            'telefone' => '(11) 93333-1111',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);
        $empresaA->update(['dono_id' => $donoA->id]);

        // Dono também possui registro em funcionarios para poder executar serviços
        $this->assertTrue(
            Funcionario::withoutGlobalScopes()
                ->where('user_id', $donoA->id)
                ->where('empresa_id', $empresaA->id)
                ->where('ativo', true)
                ->exists()
        );

        Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresaA->id,
            'nome' => 'Cliente Exclusivo Alfa',
            'telefone' => '(11) 94444-1111',
        ]);

        Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresaB->id,
            'nome' => 'Cliente Exclusivo Beta',
            'telefone' => '(11) 94444-2222',
        ]);

        Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresaA->id,
            'nome' => 'Serviço Exclusivo Alfa',
            'preco_base' => 100.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresaB->id,
            'nome' => 'Serviço Exclusivo Beta',
            'preco_base' => 200.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        // Dono A vê apenas os registros da Empresa A
        $resClientes = $this->actingAs($donoA)->get(route('clientes.index'));
        $resClientes->assertOk()
            ->assertSee('Cliente Exclusivo Alfa')
            ->assertDontSee('Cliente Exclusivo Beta');

        $resServicos = $this->actingAs($donoA)->get(route('servicos.index'));
        $resServicos->assertOk()
            ->assertSee('Serviço Exclusivo Alfa')
            ->assertDontSee('Serviço Exclusivo Beta');

        // Administrador não consegue excluir empresa com vínculos
        $admin = User::create([
            'name' => 'Admin Global',
            'email' => 'admin.global@krdetail.test',
            'password' => '12345678',
            'tipo_usuario' => 'administrador',
            'ativo' => true,
        ]);

        $this->actingAs($admin)->delete(route('configuracoes.empresas.destroy', $empresaA))
            ->assertRedirect(route('configuracoes.index', ['tab' => 'empresa']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('empresas', [
            'id' => $empresaA->id,
            'deleted_at' => null,
        ]);

        // Empresa sem vínculos pode ser excluída normalmente
        $empresaLimpa = Empresa::create([
            'nome_fantasia' => 'Empresa Sem Vínculos',
            'razao_social' => 'Empresa Sem Vínculos LTDA',
            'whatsapp' => '(11) 95555-5555',
            'email' => 'limpa@estetica.test',
            'ativo' => true,
        ]);

        $this->actingAs($admin)->delete(route('configuracoes.empresas.destroy', $empresaLimpa))
            ->assertRedirect(route('configuracoes.index', ['tab' => 'empresa']))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('empresas', ['id' => $empresaLimpa->id]);
    }
}
