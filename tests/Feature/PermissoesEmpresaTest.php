<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\MovimentacaoEstoque;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\TarefaProduto;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\AbrirOrdemServicoService;
use App\Services\MovimentarEstoqueService;
use Carbon\Carbon;
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

    public function test_funcionario_nao_pode_gerenciar_servicos_produtos_ou_movimentar_estoque_fora_da_os(): void
    {
        $dono = User::create([
            'name' => 'Dono Antifraude',
            'email' => 'dono.antifraude@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 91111-1010',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => 'Estética Antifraude',
            'razao_social' => 'Estética Antifraude LTDA',
            'whatsapp' => '(11) 91111-1010',
            'email' => 'antifraude@krdetail.test',
            'ativo' => true,
        ]);
        $dono->update(['empresa_id' => $empresa->id]);

        $admin = User::create([
            'name' => 'Admin Antifraude',
            'email' => 'admin.antifraude@krdetail.test',
            'password' => 'SenhaForte123',
            'tipo_usuario' => 'administrador',
            'ativo' => true,
        ]);

        $funcUser = User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Funcionário Operacional',
            'email' => 'func.antifraude@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 92222-2020',
            'tipo_usuario' => 'funcionario',
            'ativo' => true,
        ]);

        $servico = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'Lavagem Técnica Base',
            'preco_base' => 150.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        $produto = Produto::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'Shampoo Neutro Antifraude',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 1000,
            'estoque_minimo' => 500,
            'custo_unitario' => 50.00,
            'ativo' => true,
        ]);

        // 1. Funcionário visualiza em modo leitura, sem botões ou modais de alteração/movimentação manual
        $this->actingAs($funcUser)->get(route('servicos.index'))
            ->assertOk()
            ->assertSee('Lavagem Técnica Base')
            ->assertDontSee('Novo Serviço')
            ->assertDontSee('Cadastrar Serviço')
            ->assertDontSee('title="Editar"', false);

        $this->actingAs($funcUser)->get(route('produtos.index'))
            ->assertOk()
            ->assertSee('Shampoo Neutro Antifraude')
            ->assertDontSee('Novo Produto')
            ->assertDontSee('title="Editar Produto"', false);

        $this->actingAs($funcUser)->get(route('produtos.show', $produto))
            ->assertOk()
            ->assertSee('Shampoo Neutro Antifraude')
            ->assertDontSee('Registrar Movimentação')
            ->assertDontSee('id="modal-movimentacao"', false)
            ->assertDontSee(route('produtos.edit', $produto), false);

        $this->actingAs($funcUser)->get(route('estoque.index'))
            ->assertOk()
            ->assertSee('Shampoo Neutro Antifraude')
            ->assertDontSee('Nova Movimentação')
            ->assertDontSee('Novo Produto')
            ->assertDontSee('id="modal-nova-movimentacao"', false)
            ->assertDontSee('Reativar / Editar')
            ->assertDontSee(route('produtos.edit', $produto), false);

        // 2. Funcionário é bloqueado em todas as rotas de criação/edição/exclusão e movimentação manual (HTML e JSON)
        $this->actingAs($funcUser)->get(route('servicos.create'))
            ->assertRedirect(route('servicos.index'))
            ->assertSessionHas('error');
        $this->actingAs($funcUser)->getJson(route('servicos.create'))->assertForbidden();

        $this->actingAs($funcUser)->post(route('servicos.store'), [
            'nome' => 'Serviço Fraudulento',
            'preco_base' => 1.00,
            'tempo_estimado_minutos' => 30,
            'ativo' => true,
        ])->assertRedirect(route('servicos.index'))->assertSessionHas('error');
        $this->actingAs($funcUser)->postJson(route('servicos.store'), [
            'nome' => 'Serviço Fraudulento JSON',
            'preco_base' => 1.00,
            'tempo_estimado_minutos' => 30,
        ])->assertForbidden();
        $this->assertDatabaseMissing('servicos', ['nome' => 'Serviço Fraudulento']);

        $this->actingAs($funcUser)->get(route('servicos.edit', $servico))
            ->assertRedirect(route('servicos.index'))
            ->assertSessionHas('error');
        $this->actingAs($funcUser)->getJson(route('servicos.edit', $servico))->assertForbidden();

        $this->actingAs($funcUser)->put(route('servicos.update', $servico), [
            'nome' => 'Preço Alterado Indevidamente',
            'preco_base' => 1.00,
            'tempo_estimado_minutos' => 60,
        ])->assertRedirect(route('servicos.index'))->assertSessionHas('error');
        $this->actingAs($funcUser)->putJson(route('servicos.update', $servico), [
            'nome' => 'Preço Alterado JSON',
            'preco_base' => 1.00,
            'tempo_estimado_minutos' => 60,
        ])->assertForbidden();
        $this->assertSame('Lavagem Técnica Base', $servico->fresh()->nome);
        $this->assertEquals(150.00, (float) $servico->fresh()->preco_base);

        $this->actingAs($funcUser)->delete(route('servicos.destroy', $servico))
            ->assertRedirect(route('servicos.index'))
            ->assertSessionHas('error');
        $this->actingAs($funcUser)->deleteJson(route('servicos.destroy', $servico))->assertForbidden();
        $this->assertDatabaseHas('servicos', ['id' => $servico->id]);

        $this->actingAs($funcUser)->get(route('produtos.create'))
            ->assertRedirect(route('produtos.index'))
            ->assertSessionHas('error');
        $this->actingAs($funcUser)->getJson(route('produtos.create'))->assertForbidden();

        $this->actingAs($funcUser)->post(route('produtos.store'), [
            'nome' => 'Produto Indevido',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_unidades' => 2,
            'estoque_minimo_unidades' => 1,
            'custo_unitario' => 10.00,
        ])->assertRedirect(route('produtos.index'))->assertSessionHas('error');
        $this->actingAs($funcUser)->postJson(route('produtos.store'), [
            'nome' => 'Produto Indevido JSON',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'custo_unitario' => 10.00,
        ])->assertForbidden();
        $this->assertDatabaseMissing('produtos', ['nome' => 'Produto Indevido']);

        $this->actingAs($funcUser)->get(route('produtos.edit', $produto))
            ->assertRedirect(route('produtos.index'))
            ->assertSessionHas('error');
        $this->actingAs($funcUser)->getJson(route('produtos.edit', $produto))->assertForbidden();

        $this->actingAs($funcUser)->put(route('produtos.update', $produto), [
            'nome' => 'Produto Alterado Indevidamente',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 0,
            'estoque_minimo' => 500,
            'custo_unitario' => 1.00,
        ])->assertRedirect(route('produtos.index'))->assertSessionHas('error');
        $this->actingAs($funcUser)->patchJson(route('produtos.update', $produto), [
            'nome' => 'Produto Alterado JSON',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'custo_unitario' => 1.00,
        ])->assertForbidden();
        $this->assertSame('Shampoo Neutro Antifraude', $produto->fresh()->nome);
        $this->assertEquals(1000.000, (float) $produto->fresh()->quantidade_estoque);

        $this->actingAs($funcUser)->delete(route('produtos.destroy', $produto))
            ->assertRedirect(route('produtos.index'))
            ->assertSessionHas('error');
        $this->actingAs($funcUser)->deleteJson(route('produtos.destroy', $produto))->assertForbidden();
        $this->assertDatabaseHas('produtos', ['id' => $produto->id]);

        foreach (['entrada', 'saida', 'ajuste'] as $tipoMov) {
            $this->actingAs($funcUser)->post(route('estoque.movimentar'), [
                'produto_id' => $produto->id,
                'tipo_movimentacao' => $tipoMov,
                'modo_quantidade' => 'volume',
                'quantidade' => 100,
                'motivo' => 'Movimentação manual indevida',
            ])->assertRedirect(route('estoque.index'))->assertSessionHas('error');

            $this->actingAs($funcUser)->postJson(route('estoque.movimentar'), [
                'produto_id' => $produto->id,
                'tipo_movimentacao' => $tipoMov,
                'modo_quantidade' => 'volume',
                'quantidade' => 100,
                'motivo' => 'Movimentação manual indevida JSON',
            ])->assertForbidden();
        }
        $this->assertEquals(1000.000, (float) $produto->fresh()->quantidade_estoque);
        $this->assertSame(0, MovimentacaoEstoque::withoutGlobalScopes()->where('produto_id', $produto->id)->count());

        // 3. Trava em profundidade no MovimentarEstoqueService para movimentação manual por Funcionário
        $lancouExcecaoManual = false;
        try {
            app(MovimentarEstoqueService::class)->movimentar(
                produto: $produto,
                tipo: 'saida',
                quantidade: 100,
                usuario: $funcUser,
                motivo: 'Tentativa direta via service',
                movimentacaoManual: true
            );
        } catch (\DomainException $e) {
            $lancouExcecaoManual = true;
        }
        $this->assertTrue($lancouExcecaoManual, 'MovimentarEstoqueService deve bloquear movimentação manual de Funcionário');
        $this->assertEquals(1000.000, (float) $produto->fresh()->quantidade_estoque);

        // 4. Funcionário CONTINUA podendo dar baixa e estornar produtos DENTRO de uma Ordem de Serviço / Tarefa
        $cliente = Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente OS Antifraude',
            'telefone' => '(11) 93333-4040',
        ]);

        $veiculo = Veiculo::withoutGlobalScopes()->create([
            'cliente_id' => $cliente->id,
            'placa' => 'SEG1A23',
            'marca' => 'VW',
            'modelo' => 'Golf',
        ]);

        $inicio = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 0);
        $ag = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->copy()->addMinutes(60),
            'status' => 'confirmado',
            'origem' => 'Sistema',
        ]);
        $ag->agendamentosServicos()->create([
            'servico_id' => $servico->id,
            'valor_servico' => 150.00,
            'tempo_estimado_minutos' => 60,
        ]);

        $this->actingAs($dono);
        $os = app(AbrirOrdemServicoService::class)->abrir($ag->id);
        $tarefa = $os->tarefas()->firstOrFail();

        $this->actingAs($funcUser)->post(route('tarefas.adicionar-produto', $tarefa), [
            'produto_id' => $produto->id,
            'quantidade_utilizada' => 150,
            'observacoes' => 'Consumo legítimo na OS',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertEquals(850.000, (float) $produto->fresh()->quantidade_estoque);
        $tarefaProduto = TarefaProduto::withoutGlobalScopes()->where('tarefa_id', $tarefa->id)->firstOrFail();

        $this->actingAs($funcUser)->delete(route('tarefas.remover-produto', [$tarefa, $tarefaProduto]))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertEquals(1000.000, (float) $produto->fresh()->quantidade_estoque);

        // 5. Dono da Empresa e Administrador Geral mantêm acesso total
        $this->actingAs($dono)->post(route('servicos.store'), [
            'nome' => 'Serviço Criado Pelo Dono',
            'preco_base' => 250.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ])->assertRedirect(route('servicos.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('servicos', ['nome' => 'Serviço Criado Pelo Dono', 'empresa_id' => $empresa->id]);

        $this->actingAs($dono)->post(route('estoque.movimentar'), [
            'produto_id' => $produto->id,
            'tipo_movimentacao' => 'entrada',
            'modo_quantidade' => 'volume',
            'quantidade' => 250,
            'motivo' => 'Reposição autorizada pelo Dono',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertEquals(1250.000, (float) $produto->fresh()->quantidade_estoque);

        $this->actingAs($admin)->post(route('produtos.store'), [
            'empresa_id' => $empresa->id,
            'nome' => 'Produto Criado Pelo Admin',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_unidades' => 1,
            'estoque_minimo_unidades' => 1,
            'custo_unitario' => 45.00,
            'ativo' => true,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('produtos', ['nome' => 'Produto Criado Pelo Admin', 'empresa_id' => $empresa->id]);
    }
}
