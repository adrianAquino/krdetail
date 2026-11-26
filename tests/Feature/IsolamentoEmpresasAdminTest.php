<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\MovimentacaoEstoque;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Tarefa;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\AbrirOrdemServicoService;
use App\Services\ConcluirTarefaService;
use App\Services\IniciarTarefaService;
use App\Services\MovimentarEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsolamentoEmpresasAdminTest extends TestCase
{
    use RefreshDatabase;

    private function criarAdmin(): User
    {
        return User::create([
            'name' => 'Administrador Geral Teste',
            'email' => 'admin.isolamento@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 90000-0000',
            'tipo_usuario' => 'administrador',
            'ativo' => true,
        ]);
    }

    private function criarEmpresaCompleta(
        string $tag,
        string $nomeFantasia,
        string $cnpj,
        float $precoServico,
        float $custoProduto,
        string $placaBase
    ): array {
        $dono = User::create([
            'name' => "Dono {$tag}",
            'email' => strtolower("dono.{$tag}@krdetail.test"),
            'password' => 'SenhaForte123',
            'telefone' => '(11) 91111-0001',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => $nomeFantasia,
            'razao_social' => "{$nomeFantasia} LTDA",
            'cnpj' => $cnpj,
            'whatsapp' => '(11) 91111-0001',
            'email' => strtolower("contato.{$tag}@krdetail.test"),
            'ativo' => true,
        ]);

        $dono->update(['empresa_id' => $empresa->id]);

        $userFunc = User::create([
            'empresa_id' => $empresa->id,
            'name' => "Tecnico {$tag}",
            'email' => strtolower("func.{$tag}@krdetail.test"),
            'password' => 'SenhaForte123',
            'telefone' => '(11) 92222-0002',
            'tipo_usuario' => 'funcionario',
            'ativo' => true,
        ]);

        $funcionario = Funcionario::withoutGlobalScopes()->where('user_id', $userFunc->id)->firstOrFail();
        $funcionario->update(['cargo' => "Detailer {$tag}"]);

        $cliente = Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Cliente {$tag} Exclusivo",
            'telefone' => '(11) 93333-0003',
            'email' => strtolower("cliente.{$tag}@krdetail.test"),
        ]);

        $veiculo = Veiculo::withoutGlobalScopes()->create([
            'cliente_id' => $cliente->id,
            'placa' => $placaBase,
            'marca' => "Marca{$tag}",
            'modelo' => "Modelo{$tag}",
        ]);

        $servico = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Servico {$tag} Exclusivo",
            'descricao' => "Procedimento exclusivo {$tag}",
            'preco_base' => $precoServico,
            'tempo_estimado_minutos' => 120,
            'ativo' => true,
        ]);

        // Produto ativo já em alerta de estoque baixo para aparecer no Dashboard e Estoque
        $produtoAtivo = Produto::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Produto {$tag} Ativo",
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 1000,
            'estoque_minimo' => 1500,
            'custo_unitario' => $custoProduto,
            'ativo' => true,
        ]);

        $produtoInativo = Produto::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Produto {$tag} Inativo",
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 500,
            'estoque_minimo' => 100,
            'custo_unitario' => 50.00,
            'ativo' => false,
        ]);

        app(MovimentarEstoqueService::class)->movimentar(
            produto: $produtoAtivo,
            tipo: 'entrada',
            quantidade: 200,
            usuario: $dono,
            motivo: "Entrada Lote {$tag} Especial"
        );

        // Agendamento 1 concluído no período atual (gera OS, Tarefa concluída, consumo e Receita)
        $inicioConcluido = now()->next(\Carbon\Carbon::MONDAY)->setTime(9, 0);
        $agConcluido = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => $inicioConcluido,
            'data_fim' => $inicioConcluido->copy()->addMinutes(150),
            'status' => 'confirmado',
            'origem' => 'WhatsApp',
        ]);
        $agConcluido->agendamentosServicos()->create([
            'servico_id' => $servico->id,
            'valor_servico' => $precoServico,
            'tempo_estimado_minutos' => 120,
        ]);

        $osConcluida = app(AbrirOrdemServicoService::class)->abrir($agConcluido->id);
        $tarefaConcluida = $osConcluida->tarefas()->withoutGlobalScopes()->firstOrFail();

        app(IniciarTarefaService::class)->iniciar($tarefaConcluida, $funcionario);
        app(MovimentarEstoqueService::class)->registrarConsumoEmTarefa($tarefaConcluida, $produtoAtivo, 100, $userFunc);
        app(ConcluirTarefaService::class)->concluir($tarefaConcluida, $funcionario, $userFunc);

        // Agendamento 2 aberto no futuro (para aparecer nos Próximos Agendamentos e permitir edição/OS)
        $inicioFuturo = now()->next(\Carbon\Carbon::THURSDAY)->setTime(10, 0);
        $agAberto = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => $inicioFuturo,
            'data_fim' => $inicioFuturo->copy()->addMinutes(150),
            'status' => 'confirmado',
            'origem' => 'Telefone',
        ]);
        $agAberto->agendamentosServicos()->create([
            'servico_id' => $servico->id,
            'valor_servico' => $precoServico,
            'tempo_estimado_minutos' => 120,
        ]);

        return [
            'dono' => $dono,
            'empresa' => $empresa,
            'userFunc' => $userFunc,
            'funcionario' => $funcionario,
            'cliente' => $cliente,
            'veiculo' => $veiculo,
            'servico' => $servico,
            'produtoAtivo' => $produtoAtivo->fresh(),
            'produtoInativo' => $produtoInativo,
            'agConcluido' => $agConcluido->fresh(),
            'osConcluida' => $osConcluida->fresh(),
            'agAberto' => $agAberto,
        ];
    }

    public function test_administrador_visualiza_visao_geral_e_filtra_por_empresa_em_cada_modulo(): void
    {
        $admin = $this->criarAdmin();
        $alfa = $this->criarEmpresaCompleta('Alfa', 'Estética Alfa Prime', '11.222.333/0001-81', 800.00, 100.00, 'ALF1A23');
        $beta = $this->criarEmpresaCompleta('Beta', 'Estética Beta Garage', '11.444.777/0001-61', 1200.00, 150.00, 'BET2B34');

        $alfaId = $alfa['empresa']->id;
        $betaId = $beta['empresa']->id;

        // 1. Dashboard: Visão Geral -> apenas Alfa -> apenas Beta -> retorno à Visão Geral (sem vazamento)
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('KR Detail')
            ->assertSee('Visão Geral')
            ->assertSee('Cliente Alfa Exclusivo')
            ->assertSee('Cliente Beta Exclusivo')
            ->assertSee('Produto Alfa Ativo')
            ->assertSee('Produto Beta Ativo')
            ->assertSee('R$ 2.000,00');

        $this->actingAs($admin)->get(route('dashboard', ['empresa_id' => $alfaId]))
            ->assertOk()
            ->assertSee('Cliente Alfa Exclusivo')
            ->assertSee('Produto Alfa Ativo')
            ->assertSee('R$ 800,00')
            ->assertDontSee('Cliente Beta Exclusivo')
            ->assertDontSee('Produto Beta Ativo');

        $this->actingAs($admin)->get(route('dashboard', ['empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee('Cliente Beta Exclusivo')
            ->assertSee('Produto Beta Ativo')
            ->assertSee('R$ 1.200,00')
            ->assertDontSee('Cliente Alfa Exclusivo')
            ->assertDontSee('Produto Alfa Ativo');

        // Próxima requisição sem empresa_id volta automaticamente à Visão Geral
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cliente Alfa Exclusivo')
            ->assertSee('Cliente Beta Exclusivo');

        // 2. Serviços
        $this->actingAs($admin)->get(route('servicos.index'))
            ->assertOk()
            ->assertSee('Servico Alfa Exclusivo')
            ->assertSee('Servico Beta Exclusivo');
        $this->actingAs($admin)->get(route('servicos.index', ['empresa_id' => $alfaId]))
            ->assertOk()
            ->assertSee('Servico Alfa Exclusivo')
            ->assertDontSee('Servico Beta Exclusivo');
        $this->actingAs($admin)->get(route('servicos.index', ['empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee('Servico Beta Exclusivo')
            ->assertDontSee('Servico Alfa Exclusivo');

        // 3. Produtos e Estoque (abas produtos, inativos e movimentacoes)
        $this->actingAs($admin)->get(route('produtos.index'))
            ->assertOk()
            ->assertSee('Produto Alfa Ativo')
            ->assertSee('Produto Beta Ativo');
        $this->actingAs($admin)->get(route('produtos.index', ['empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee('Produto Beta Ativo')
            ->assertDontSee('Produto Alfa Ativo');

        $this->actingAs($admin)->get(route('estoque.index', ['tab' => 'produtos', 'empresa_id' => $alfaId]))
            ->assertOk()
            ->assertSee('Produto Alfa Ativo')
            ->assertDontSee('Produto Beta Ativo');
        $this->actingAs($admin)->get(route('estoque.index', ['tab' => 'inativos', 'empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee('Produto Beta Inativo')
            ->assertDontSee('Produto Alfa Inativo');
        $this->actingAs($admin)->get(route('estoque.index', ['tab' => 'movimentacoes', 'empresa_id' => $alfaId]))
            ->assertOk()
            ->assertSee('Entrada Lote Alfa Especial')
            ->assertDontSee('Entrada Lote Beta Especial');

        // 4. Agendamentos e Ordens de Serviço
        $this->actingAs($admin)->get(route('agendamentos.index'))
            ->assertOk()
            ->assertSee('ALF1A23')
            ->assertSee('BET2B34');
        $this->actingAs($admin)->get(route('agendamentos.index', ['empresa_id' => $alfaId]))
            ->assertOk()
            ->assertSee('ALF1A23')
            ->assertDontSee('BET2B34');

        $this->actingAs($admin)->get(route('ordens-servico.index'))
            ->assertOk()
            ->assertSee($alfa['osConcluida']->numero)
            ->assertSee($beta['osConcluida']->numero);
        $this->actingAs($admin)->get(route('ordens-servico.index', ['empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee($beta['osConcluida']->numero)
            ->assertDontSee($alfa['osConcluida']->numero);

        // 5. Relatórios (financeiro + operacional por empresa)
        $this->actingAs($admin)->get(route('relatorios.index'))
            ->assertOk()
            ->assertSee('R$ 2.000,00')
            ->assertSee('Tecnico Alfa')
            ->assertSee('Tecnico Beta');

        $this->actingAs($admin)->get(route('relatorios.index', ['empresa_id' => $alfaId]))
            ->assertOk()
            ->assertSee('R$ 800,00')
            ->assertSee('Tecnico Alfa')
            ->assertSee($alfa['osConcluida']->numero)
            ->assertDontSee('Tecnico Beta')
            ->assertDontSee($beta['osConcluida']->numero);

        $this->actingAs($admin)->get(route('relatorios.index', ['empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee('R$ 1.200,00')
            ->assertSee('Tecnico Beta')
            ->assertSee($beta['osConcluida']->numero)
            ->assertDontSee('Tecnico Alfa')
            ->assertDontSee($alfa['osConcluida']->numero);

        // 6. Clientes, Veículos e Equipe (Configurações)
        $this->actingAs($admin)->get(route('clientes.index', ['empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee('Cliente Beta Exclusivo')
            ->assertDontSee('Cliente Alfa Exclusivo');

        $this->actingAs($admin)->get(route('veiculos.index', ['empresa_id' => $alfaId]))
            ->assertOk()
            ->assertSee('ALF1A23')
            ->assertDontSee('BET2B34');

        $respEquipeBeta = $this->actingAs($admin)->get(route('configuracoes.index', ['tab' => 'equipe', 'empresa_id' => $betaId]));
        $respEquipeBeta->assertOk();
        $tabEquipeHtml = \Illuminate\Support\Str::between($respEquipeBeta->getContent(), 'id="tab-equipe"', 'id="tab-preferencias"');
        $this->assertStringContainsString('Tecnico Beta', $tabEquipeHtml);
        $this->assertStringNotContainsString('Tecnico Alfa', $tabEquipeHtml);

        $respEquipeGeral = $this->actingAs($admin)->get(route('configuracoes.index', ['tab' => 'equipe']));
        $respEquipeGeral->assertOk();
        $tabEquipeGeralHtml = \Illuminate\Support\Str::between($respEquipeGeral->getContent(), 'id="tab-equipe"', 'id="tab-preferencias"');
        $this->assertStringContainsString('Tecnico Alfa', $tabEquipeGeralHtml);
        $this->assertStringContainsString('Tecnico Beta', $tabEquipeGeralHtml);

        // ID inexistente cai com segurança na Visão Geral sem quebrar
        $this->actingAs($admin)->get(route('servicos.index', ['empresa_id' => 999999]))
            ->assertOk()
            ->assertSee('Servico Alfa Exclusivo')
            ->assertSee('Servico Beta Exclusivo');
    }

    public function test_administrador_cria_e_edita_registros_na_empresa_escolhida_sem_misturar_empresas(): void
    {
        $admin = $this->criarAdmin();
        $alfa = $this->criarEmpresaCompleta('Alfa', 'Estética Alfa Prime', '11.222.333/0001-81', 800.00, 100.00, 'ALF1A23');
        $beta = $this->criarEmpresaCompleta('Beta', 'Estética Beta Garage', '11.444.777/0001-61', 1200.00, 150.00, 'BET2B34');
        $betaId = $beta['empresa']->id;

        // Admin cria Serviço, Produto e Cliente explicitamente para a 2ª empresa (Beta)
        $this->actingAs($admin)->post(route('servicos.store'), [
            'empresa_id' => $betaId,
            'nome' => 'Vitrificação Cerâmica Admin Beta',
            'preco_base' => 1500.00,
            'tempo_estimado_minutos' => 180,
            'ativo' => '1',
        ])->assertRedirect(route('servicos.index', ['empresa_id' => $betaId]));

        $this->assertDatabaseHas('servicos', [
            'nome' => 'Vitrificação Cerâmica Admin Beta',
            'empresa_id' => $betaId,
        ]);

        $this->actingAs($admin)->post(route('produtos.store'), [
            'empresa_id' => $betaId,
            'nome' => 'Nano Coating Admin Beta',
            'unidade_medida' => 'ml',
            'volume_unitario' => 100,
            'quantidade_unidades' => 2,
            'estoque_minimo_unidades' => 1,
            'custo_unitario' => 250.00,
            'ativo' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('produtos', [
            'nome' => 'Nano Coating Admin Beta',
            'empresa_id' => $betaId,
        ]);

        $this->actingAs($admin)->post(route('clientes.store'), [
            'empresa_id' => $betaId,
            'nome' => 'Cliente Admin Cadastrado Beta',
            'telefone' => '(11) 98888-7777',
            'email' => 'cli.admin.beta@krdetail.test',
        ])->assertRedirect();

        $this->assertDatabaseHas('clientes', [
            'nome' => 'Cliente Admin Cadastrado Beta',
            'empresa_id' => $betaId,
        ]);

        // Ao criar agendamento para Beta, vê apenas clientes e serviços de Beta
        $this->actingAs($admin)->get(route('agendamentos.create', ['empresa_id' => $betaId]))
            ->assertOk()
            ->assertSee('Cliente Beta Exclusivo')
            ->assertSee('Servico Beta Exclusivo')
            ->assertDontSee('Cliente Alfa Exclusivo')
            ->assertDontSee('Servico Alfa Exclusivo');

        // Ao editar agendamento de Beta, vê apenas serviços de Beta
        $this->actingAs($admin)->get(route('agendamentos.edit', $beta['agAberto']))
            ->assertOk()
            ->assertSee('Servico Beta Exclusivo')
            ->assertDontSee('Servico Alfa Exclusivo');

        // Não pode misturar cliente de Alfa com serviço de Beta
        $dataValida = now()->next(\Carbon\Carbon::FRIDAY)->setTime(11, 0)->format('Y-m-d\TH:i');
        $this->actingAs($admin)->post(route('agendamentos.store'), [
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $alfa['veiculo']->id,
            'data_inicio' => $dataValida,
            'servicos' => [['servico_id' => $beta['servico']->id]],
        ])->assertSessionHasErrors('servicos');
    }

    public function test_dono_e_funcionario_ficam_estritamente_isolados_mesmo_tentando_bypass_por_url_ou_post(): void
    {
        $this->criarAdmin();
        $alfa = $this->criarEmpresaCompleta('Alfa', 'Estética Alfa Prime', '11.222.333/0001-81', 800.00, 100.00, 'ALF1A23');
        $beta = $this->criarEmpresaCompleta('Beta', 'Estética Beta Garage', '11.444.777/0001-61', 1200.00, 150.00, 'BET2B34');
        $betaId = $beta['empresa']->id;
        $alfaId = $alfa['empresa']->id;

        // 1. Tentativa de bypass na URL com ?empresa_id=beta para Dono Alfa e Funcionário Alfa
        foreach ([$alfa['dono'], $alfa['userFunc']] as $usuarioAlfa) {
            $this->actingAs($usuarioAlfa)->get(route('dashboard', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('Cliente Alfa Exclusivo')
                ->assertDontSee('Cliente Beta Exclusivo')
                ->assertDontSee('Todas as empresas (Visão Geral)');

            $this->actingAs($usuarioAlfa)->get(route('servicos.index', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('Servico Alfa Exclusivo')
                ->assertDontSee('Servico Beta Exclusivo');

            $this->actingAs($usuarioAlfa)->get(route('produtos.index', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('Produto Alfa Ativo')
                ->assertDontSee('Produto Beta Ativo');

            $this->actingAs($usuarioAlfa)->get(route('estoque.index', ['tab' => 'movimentacoes', 'empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('Entrada Lote Alfa Especial')
                ->assertDontSee('Entrada Lote Beta Especial');

            $this->actingAs($usuarioAlfa)->get(route('agendamentos.index', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('ALF1A23')
                ->assertDontSee('BET2B34');

            $this->actingAs($usuarioAlfa)->get(route('ordens-servico.index', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee($alfa['osConcluida']->numero)
                ->assertDontSee($beta['osConcluida']->numero);

            $this->actingAs($usuarioAlfa)->get(route('relatorios.index', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('R$ 800,00')
                ->assertSee('Tecnico Alfa')
                ->assertDontSee('Tecnico Beta')
                ->assertDontSee('R$ 1.200,00');

            $this->actingAs($usuarioAlfa)->get(route('clientes.index', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('Cliente Alfa Exclusivo')
                ->assertDontSee('Cliente Beta Exclusivo');

            $this->actingAs($usuarioAlfa)->get(route('veiculos.index', ['empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('ALF1A23')
                ->assertDontSee('BET2B34');

            $this->actingAs($usuarioAlfa)->get(route('configuracoes.index', ['tab' => 'equipe', 'empresa_id' => $betaId]))
                ->assertOk()
                ->assertSee('Tecnico Alfa')
                ->assertDontSee('Tecnico Beta');
        }

        // 2. Spoofing de empresa_id no POST por Dono Alfa é ignorado e grava na empresa Alfa
        $this->actingAs($alfa['dono'])->post(route('servicos.store'), [
            'empresa_id' => $betaId,
            'nome' => 'Servico Spoof Tentativa',
            'preco_base' => 150.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('servicos', [
            'nome' => 'Servico Spoof Tentativa',
            'empresa_id' => $alfaId,
        ]);

        $this->actingAs($alfa['dono'])->post(route('produtos.store'), [
            'empresa_id' => $betaId,
            'nome' => 'Produto Spoof Tentativa',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_unidades' => 2,
            'estoque_minimo_unidades' => 1,
            'custo_unitario' => 60.00,
            'ativo' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('produtos', [
            'nome' => 'Produto Spoof Tentativa',
            'empresa_id' => $alfaId,
        ]);

        $this->actingAs($alfa['dono'])->post(route('clientes.store'), [
            'empresa_id' => $betaId,
            'nome' => 'Cliente Spoof Tentativa',
            'telefone' => '(11) 96666-7777',
        ])->assertRedirect();
        $this->assertDatabaseHas('clientes', [
            'nome' => 'Cliente Spoof Tentativa',
            'empresa_id' => $alfaId,
        ]);

        // 3. Acesso e mutação cross-tenant em recursos de Beta retornam 404 / bloqueio
        $this->actingAs($alfa['dono'])->get(route('servicos.edit', $beta['servico']))->assertNotFound();
        $this->actingAs($alfa['dono'])->put(route('servicos.update', $beta['servico']), [
            'nome' => 'Alterado Indevido',
            'preco_base' => 1.00,
            'tempo_estimado_minutos' => 30,
        ])->assertNotFound();
        $this->actingAs($alfa['dono'])->delete(route('servicos.destroy', $beta['servico']))->assertNotFound();

        $this->actingAs($alfa['dono'])->get(route('produtos.edit', $beta['produtoAtivo']))->assertNotFound();
        $this->actingAs($alfa['dono'])->delete(route('produtos.destroy', $beta['produtoInativo']))->assertNotFound();

        $this->actingAs($alfa['dono'])->get(route('agendamentos.edit', $beta['agAberto']))->assertNotFound();
        $this->actingAs($alfa['dono'])->delete(route('agendamentos.destroy', $beta['agAberto']))->assertNotFound();

        $this->actingAs($alfa['dono'])->post(route('ordens-servico.store'), [
            'agendamento_id' => $beta['agAberto']->id,
        ])->assertNotFound();

        $this->assertSame('Servico Beta Exclusivo', $beta['servico']->fresh()->nome);
        $this->assertNull($beta['agAberto']->fresh()->ordemServico);
    }

    public function test_empresas_possuem_servicos_estoque_e_boxes_independentes_e_os_preserva_empresa_apos_desvinculo_de_cliente(): void
    {
        $alfa = $this->criarEmpresaCompleta('Alfa', 'Estética Alfa Prime', '11.222.333/0001-81', 800.00, 100.00, 'ALF1A23');
        $beta = $this->criarEmpresaCompleta('Beta', 'Estética Beta Garage', '11.444.777/0001-61', 1200.00, 150.00, 'BET2B34');

        // 1. Mesmo nome de serviço e produto em empresas diferentes com particularidades próprias
        $this->actingAs($alfa['dono'])->post(route('servicos.store'), [
            'nome' => 'Lavagem Detalhada Especial',
            'preco_base' => 150.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => '1',
        ])->assertRedirect();

        $this->actingAs($beta['dono'])->post(route('servicos.store'), [
            'nome' => 'Lavagem Detalhada Especial',
            'preco_base' => 290.00,
            'tempo_estimado_minutos' => 120,
            'ativo' => '1',
        ])->assertRedirect();

        $servAlfa = Servico::withoutGlobalScopes()->where('empresa_id', $alfa['empresa']->id)->where('nome', 'Lavagem Detalhada Especial')->firstOrFail();
        $servBeta = Servico::withoutGlobalScopes()->where('empresa_id', $beta['empresa']->id)->where('nome', 'Lavagem Detalhada Especial')->firstOrFail();
        $this->assertSame(150.0, (float) $servAlfa->preco_base);
        $this->assertSame(290.0, (float) $servBeta->preco_base);

        // 2. Lotar os 4 boxes de Alfa na quarta-feira às 10:00 NÃO bloqueia agendamento em Beta no mesmo horário
        $this->actingAs($alfa['dono']);
        $quarta10 = now()->next(\Carbon\Carbon::WEDNESDAY)->setTime(10, 0, 0);
        for ($i = 1; $i <= 4; $i++) {
            $vBoxAlfa = Veiculo::withoutGlobalScopes()->create([
                'cliente_id' => $alfa['cliente']->id,
                'placa' => "BOX1A0{$i}",
                'marca' => 'VW',
                'modelo' => "Gol {$i}",
            ]);
            Agendamento::withoutGlobalScopes()->create([
                'empresa_id' => $alfa['empresa']->id,
                'cliente_id' => $alfa['cliente']->id,
                'veiculo_id' => $vBoxAlfa->id,
                'data_inicio' => $quarta10,
                'data_fim' => $quarta10->copy()->addMinutes(90),
                'status' => 'confirmado',
                'origem' => 'WhatsApp',
            ]);
        }

        // 5º carro de Alfa é bloqueado; 1º carro de Beta no mesmo horário é aceito
        $vQuintoAlfa = Veiculo::withoutGlobalScopes()->create([
            'cliente_id' => $alfa['cliente']->id,
            'placa' => 'BOX1A05',
            'marca' => 'VW',
            'modelo' => 'Polo',
        ]);
        $this->actingAs($alfa['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $vQuintoAlfa->id,
            'data_inicio' => $quarta10->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servAlfa->id]],
        ])->assertSessionHasErrors('data_inicio');

        $this->actingAs($beta['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $beta['cliente']->id,
            'veiculo_id' => $beta['veiculo']->id,
            'data_inicio' => $quarta10->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servBeta->id]],
        ])->assertRedirect();

        // 3. Cliente com OS aberta na Empresa Alfa muda de empresa para Beta -> OS de Alfa continua estritamente isolada a Alfa
        $this->actingAs($alfa['dono']);
        $osAlfaAberta = app(AbrirOrdemServicoService::class)->abrir($alfa['agAberto']->id);
        $tarefaAlfaAberta = $osAlfaAberta->tarefas()->withoutGlobalScopes()->firstOrFail();

        Cliente::withoutEvents(function () use ($alfa, $beta) {
            $alfa['cliente']->update(['empresa_id' => $beta['empresa']->id]);
        });

        // Na tela da tarefa de Alfa, NÃO deve exibir funcionário nem produto de Beta
        $this->actingAs($alfa['dono'])->get(route('tarefas.show', $tarefaAlfaAberta))
            ->assertOk()
            ->assertSee('Tecnico Alfa')
            ->assertSee('Produto Alfa Ativo')
            ->assertDontSee('Tecnico Beta')
            ->assertDontSee('Produto Beta Ativo');

        // Tentar iniciar/consumir com funcionário ou produto de Beta na tarefa de Alfa deve ser bloqueado
        $this->actingAs($alfa['dono'])->post(route('tarefas.iniciar', $tarefaAlfaAberta), [
            'funcionario_id' => $beta['funcionario']->id,
        ])->assertSessionHasErrors('funcionario_id');

        $this->actingAs($alfa['dono'])->post(route('tarefas.adicionar-produto', $tarefaAlfaAberta), [
            'produto_id' => $beta['produtoAtivo']->id,
            'quantidade_utilizada' => 25,
        ])->assertSessionHasErrors('produto_id');

        // Executar e concluir a tarefa com funcionário de Alfa gera receita financeira estritamente em Alfa
        $this->actingAs($alfa['dono'])->post(route('tarefas.iniciar', $tarefaAlfaAberta), [
            'funcionario_id' => $alfa['funcionario']->id,
        ])->assertSessionHas('success');

        $this->actingAs($alfa['dono'])->post(route('tarefas.concluir', $tarefaAlfaAberta), [
            'funcionario_id' => $alfa['funcionario']->id,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('lancamentos_financeiros', [
            'tarefa_id' => $tarefaAlfaAberta->id,
            'empresa_id' => $alfa['empresa']->id,
            'tipo' => 'receita',
            'valor' => 800.00,
        ]);
    }
}
