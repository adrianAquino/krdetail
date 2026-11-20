<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\Notificacao;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Tarefa;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\AbrirOrdemServicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SegurancaMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    private function criarAmbienteEmpresa(string $sufixo, string $cnpj): array
    {
        $dono = User::create([
            'name' => "Dono {$sufixo}",
            'email' => "dono.{$sufixo}@krdetail.test",
            'password' => 'SenhaForte123',
            'telefone' => '(11) 90000-0001',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => "Estética {$sufixo}",
            'razao_social' => "Estética {$sufixo} LTDA",
            'cnpj' => $cnpj,
            'whatsapp' => '(11) 90000-0001',
            'email' => "contato.{$sufixo}@krdetail.test",
            'ativo' => true,
        ]);

        $dono->update(['empresa_id' => $empresa->id]);

        $userFunc = User::create([
            'empresa_id' => $empresa->id,
            'name' => "Técnico {$sufixo}",
            'email' => "func.{$sufixo}@krdetail.test",
            'password' => 'SenhaForte123',
            'telefone' => '(11) 90000-0002',
            'tipo_usuario' => 'funcionario',
            'ativo' => true,
        ]);

        $funcionario = Funcionario::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $userFunc->id],
            [
                'empresa_id' => $empresa->id,
                'cargo' => 'Detailer',
                'ativo' => true,
            ]
        );

        $cliente = Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Cliente {$sufixo}",
            'telefone' => '(11) 90000-0003',
            'email' => "cliente.{$sufixo}@krdetail.test",
        ]);

        $veiculo = Veiculo::withoutGlobalScopes()->create([
            'cliente_id' => $cliente->id,
            'placa' => strtoupper(substr(md5($sufixo), 0, 3)) . '1' . strtoupper(substr(md5($sufixo), 3, 1)) . '23',
            'marca' => 'BMW',
            'modelo' => '320i',
        ]);

        $servico = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Vitrificação {$sufixo}",
            'preco_base' => 500.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        $produto = Produto::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Composto {$sufixo}",
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 1000,
            'estoque_minimo' => 200,
            'custo_unitario' => 100.00,
            'ativo' => true,
        ]);

        $inicio = now()->next(\Carbon\Carbon::MONDAY)->setTime(10, 0);
        $agendamento = Agendamento::withoutGlobalScopes()->create([
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->copy()->addMinutes(90),
            'status' => 'confirmado',
            'origem' => 'WhatsApp',
        ]);

        $agendamento->agendamentosServicos()->create([
            'servico_id' => $servico->id,
            'valor_servico' => 500.00,
            'tempo_estimado_minutos' => 60,
        ]);

        $os = app(AbrirOrdemServicoService::class)->abrir($agendamento->id);
        $tarefa = $os->tarefas()->withoutGlobalScopes()->first();

        $lancamento = LancamentoFinanceiro::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $dono->id,
            'tipo' => 'receita',
            'categoria' => 'Serviços',
            'descricao' => "Receita {$sufixo}",
            'valor' => 500.00,
            'data_lancamento' => now()->toDateString(),
        ]);

        $notificacao = Notificacao::withoutGlobalScopes()->create([
            'cliente_id' => $cliente->id,
            'tipo' => 'os_concluida',
            'titulo' => "Aviso {$sufixo}",
            'mensagem' => 'Pronto',
            'status' => 'pendente',
        ]);

        return compact(
            'dono',
            'empresa',
            'userFunc',
            'funcionario',
            'cliente',
            'veiculo',
            'servico',
            'produto',
            'agendamento',
            'os',
            'tarefa',
            'lancamento',
            'notificacao'
        );
    }

    public function test_isolamento_estrito_entre_empresas_em_modelos_e_rotas(): void
    {
        $empA = $this->criarAmbienteEmpresa('alfa', '11.222.333/0001-81');
        $empB = $this->criarAmbienteEmpresa('beta', '11.444.777/0001-61');

        $this->actingAs($empA['dono']);

        $this->assertTrue(Cliente::whereKey($empA['cliente']->id)->exists());
        $this->assertFalse(Cliente::whereKey($empB['cliente']->id)->exists());
        $this->assertFalse(Veiculo::whereKey($empB['veiculo']->id)->exists());
        $this->assertFalse(Agendamento::whereKey($empB['agendamento']->id)->exists());
        $this->assertFalse(OrdemServico::whereKey($empB['os']->id)->exists());
        $this->assertFalse(Tarefa::whereKey($empB['tarefa']->id)->exists());
        $this->assertFalse(Produto::whereKey($empB['produto']->id)->exists());
        $this->assertFalse(Servico::whereKey($empB['servico']->id)->exists());
        $this->assertFalse(LancamentoFinanceiro::whereKey($empB['lancamento']->id)->exists());
        $this->assertFalse(Notificacao::whereKey($empB['notificacao']->id)->exists());

        $this->get(route('clientes.show', $empB['cliente']->id))->assertNotFound();
        $this->get(route('veiculos.show', $empB['veiculo']->id))->assertNotFound();
        $this->get(route('agendamentos.show', $empB['agendamento']->id))->assertNotFound();
        $this->get(route('ordens-servico.show', $empB['os']->id))->assertNotFound();
        $this->get(route('tarefas.show', $empB['tarefa']->id))->assertNotFound();
        $this->get(route('produtos.show', $empB['produto']->id))->assertNotFound();
    }

    public function test_fail_closed_quando_dono_ou_funcionario_nao_possui_empresa_ativa(): void
    {
        $this->criarAmbienteEmpresa('alfa', '11.222.333/0001-81');

        $funcSemEmpresa = User::create([
            'empresa_id' => null,
            'name' => 'Sem Empresa',
            'email' => 'sem.empresa@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 98888-1111',
            'tipo_usuario' => 'funcionario',
            'ativo' => true,
        ]);

        $this->actingAs($funcSemEmpresa);

        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Veiculo::count());
        $this->assertSame(0, Agendamento::count());
        $this->assertSame(0, OrdemServico::count());
        $this->assertSame(0, LancamentoFinanceiro::count());
        $this->assertSame(0, Notificacao::count());
    }

    public function test_bloqueia_idor_cross_tenant_em_veiculos_estoque_e_tarefas(): void
    {
        $empA = $this->criarAmbienteEmpresa('alfa', '11.222.333/0001-81');
        $empB = $this->criarAmbienteEmpresa('beta', '11.444.777/0001-61');

        $this->actingAs($empA['dono']);

        // Veículo tentando vincular cliente da Empresa B
        $this->post(route('veiculos.store'), [
            'cliente_id' => $empB['cliente']->id,
            'placa' => 'XYZ9A99',
            'marca' => 'Porsche',
            'modelo' => 'Macan',
        ])->assertSessionHasErrors('cliente_id');

        $this->assertDatabaseMissing('veiculos', ['placa' => 'XYZ9A99']);

        // Estoque movimentando produto da Empresa B
        $this->post(route('estoque.movimentar'), [
            'produto_id' => $empB['produto']->id,
            'tipo_movimentacao' => 'entrada',
            'modo_quantidade' => 'volume',
            'quantidade' => 100,
            'motivo' => 'Tentativa IDOR',
        ])->assertSessionHasErrors('produto_id');

        $this->assertEquals(1000.000, (float) $empB['produto']->fresh()->quantidade_estoque);

        // Atribuir tarefa da Empresa A para funcionário da Empresa B
        $this->patch(route('tarefas.atribuir', $empA['tarefa']), [
            'funcionario_id' => $empB['funcionario']->id,
        ])->assertSessionHasErrors('funcionario_id');

        $this->assertNull($empA['tarefa']->fresh()->funcionario_id);

        // Consumir produto da Empresa B em tarefa da Empresa A
        $this->post(route('tarefas.adicionar-produto', $empA['tarefa']), [
            'produto_id' => $empB['produto']->id,
            'quantidade_utilizada' => 50,
        ])->assertSessionHasErrors('produto_id');

        $this->assertEquals(1000.000, (float) $empB['produto']->fresh()->quantidade_estoque);
    }

    public function test_middleware_ensure_user_is_active_bloqueia_html_e_json(): void
    {
        $empA = $this->criarAmbienteEmpresa('alfa', '11.222.333/0001-81');
        $empA['userFunc']->update(['ativo' => false]);

        $this->actingAs($empA['userFunc'])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->actingAs($empA['userFunc'])
            ->getJson(route('dashboard'))
            ->assertStatus(403);

        $this->assertGuest();

        // Cliente ativo tentando endpoint exclusivo da equipe em JSON recebe 403
        $clienteUser = User::create([
            'empresa_id' => $empA['empresa']->id,
            'name' => 'Cliente Portal',
            'email' => 'portal.403@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 93333-4444',
            'tipo_usuario' => 'cliente',
            'ativo' => true,
        ]);

        $this->actingAs($clienteUser)
            ->getJson(route('clientes.index'))
            ->assertStatus(403);
    }

    public function test_anti_takeover_no_cadastro_publico_exige_confirmacao_e_preserva_empresa(): void
    {
        $empB = $this->criarAmbienteEmpresa('beta', '11.444.777/0001-61');

        $clienteOffline = Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empB['empresa']->id,
            'nome' => 'João Offline',
            'cpf_cnpj' => '52998224725',
            'telefone' => '(11) 95555-4444',
            'email' => 'joao.offline@krdetail.test',
        ]);

        // Tentativa com CPF divergente NÃO deve tomar a conta do cliente existente
        $this->post(route('register.post'), [
            'name' => 'Invasor',
            'email' => 'joao.offline@krdetail.test',
            'cpf_cnpj' => '11144477735',
            'telefone' => '(11) 90000-9999',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ]);

        $clienteOffline->refresh();
        $this->assertNull($clienteOffline->user_id);
        $this->assertSame($empB['empresa']->id, (int) $clienteOffline->empresa_id);

        auth()->logout();

        // Cadastro confirmando CPF correto vincula o cliente e preserva empresa_id da Empresa B
        $this->post(route('register.post'), [
            'name' => 'João Real',
            'email' => 'joao.offline@krdetail.test',
            'cpf_cnpj' => '529.982.247-25',
            'telefone' => '(11) 95555-4444',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ])->assertRedirect(route('dashboard'));

        $clienteOffline->refresh();
        $this->assertNotNull($clienteOffline->user_id);
        $this->assertSame($empB['empresa']->id, (int) $clienteOffline->empresa_id);
        $this->assertSame($empB['empresa']->id, (int) User::find($clienteOffline->user_id)->empresa_id);
    }

    public function test_anti_escalacao_em_configuracoes_impede_autodegradacao_alteracao_dono_e_troca_senha_sem_atual(): void
    {
        $empA = $this->criarAmbienteEmpresa('alfa', '11.222.333/0001-81');

        $outroDono = User::create([
            'empresa_id' => $empA['empresa']->id,
            'name' => 'Dono Secundário',
            'email' => 'dono.sec@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 96666-5555',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        // Dono secundário não pode tomar dono_id nem se autodegradar
        $this->actingAs($outroDono);

        $this->put(route('configuracoes.empresas.update', $empA['empresa']->id), [
            'dono_id' => $outroDono->id,
            'nome_fantasia' => $empA['empresa']->nome_fantasia,
            'razao_social' => $empA['empresa']->razao_social,
            'whatsapp' => $empA['empresa']->whatsapp,
            'email' => $empA['empresa']->email,
        ]);

        $this->assertSame($empA['dono']->id, (int) $empA['empresa']->fresh()->dono_id);

        // Não pode desativar a si mesmo
        $this->patch(route('configuracoes.usuarios.toggle-status', $outroDono->id))
            ->assertSessionHas('error');
        $this->assertTrue($outroDono->fresh()->ativo);

        // Alteração de senha no perfil exige current_password correta
        $this->put(route('configuracoes.perfil.update'), [
            'name' => $outroDono->name,
            'email' => $outroDono->email,
            'password' => 'NovaSenhaForte99',
            'password_confirmation' => 'NovaSenhaForte99',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('SenhaForte123', $outroDono->fresh()->password));

        $this->put(route('configuracoes.perfil.update'), [
            'name' => $outroDono->name,
            'email' => $outroDono->email,
            'current_password' => 'SenhaForte123',
            'password' => 'NovaSenhaForte99',
            'password_confirmation' => 'NovaSenhaForte99',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('NovaSenhaForte99', $outroDono->fresh()->password));
    }
}
