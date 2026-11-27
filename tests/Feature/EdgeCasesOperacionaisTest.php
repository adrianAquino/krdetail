<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\OrdemServico;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Tarefa;
use App\Models\TarefaProduto;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\AbrirOrdemServicoService;
use App\Services\ConcluirTarefaService;
use App\Services\IniciarTarefaService;
use App\Services\MovimentarEstoqueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCasesOperacionaisTest extends TestCase
{
    use RefreshDatabase;

    private function criarContextoOficina(): array
    {
        $dono = User::create([
            'name' => 'Álex Kraus',
            'email' => 'alex.ops@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 91234-5678',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => 'Kraus Edge Ops',
            'razao_social' => 'Kraus Edge Ops LTDA',
            'whatsapp' => '(11) 91234-5678',
            'email' => 'ops@krdetail.test',
            'ativo' => true,
        ]);

        $dono->update(['empresa_id' => $empresa->id]);

        $funcionario = Funcionario::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $dono->id],
            [
                'empresa_id' => $empresa->id,
                'cargo' => 'Especialista',
                'ativo' => true,
            ]
        );

        $cliente = Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'Érica Clientes',
            'telefone' => '(11) 94444-3333',
            'email' => 'erica.ops@krdetail.test',
        ]);

        $servico60 = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'Lavagem Técnica',
            'preco_base' => 150.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        return compact('dono', 'empresa', 'funcionario', 'cliente', 'servico60');
    }

    public function test_capacidade_de_quatro_boxes_sweep_line_e_limite_fim_expediente(): void
    {
        $ctx = $this->criarContextoOficina();
        for ($f = 1; $f <= 3; $f++) {
            User::create([
                'empresa_id' => $ctx['empresa']->id,
                'name' => "Tecnico Box {$f}",
                'email' => "tec.edge{$f}@krdetail.test",
                'password' => 'SenhaForte123',
                'telefone' => "(11) 91234-000{$f}",
                'tipo_usuario' => 'funcionario',
                'ativo' => true,
            ]);
        }
        $this->actingAs($ctx['dono']);

        $segunda = Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0);

        for ($i = 1; $i <= 4; $i++) {
            $veic = Veiculo::create([
                'cliente_id' => $ctx['cliente']->id,
                'placa' => "BOX{$i}A11",
                'marca' => 'VW',
                'modelo' => "Golf {$i}",
            ]);

            $this->post(route('agendamentos.store'), [
                'cliente_id' => $ctx['cliente']->id,
                'veiculo_id' => $veic->id,
                'data_inicio' => $segunda->format('Y-m-d\TH:i'),
                'status' => 'confirmado',
                'origem' => 'WhatsApp',
                'servicos' => [
                    $ctx['servico60']->id => ['servico_id' => $ctx['servico60']->id],
                ],
            ])->assertRedirect()
                ->assertSessionHas('success');
        }

        // 5º veículo no mesmo horário (09:30 -> 11:00 sobrepondo 4 boxes) deve ser bloqueado
        $veic5 = Veiculo::create([
            'cliente_id' => $ctx['cliente']->id,
            'placa' => 'BOX5A11',
            'marca' => 'Audi',
            'modelo' => 'A3',
        ]);

        $this->post(route('agendamentos.store'), [
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $veic5->id,
            'data_inicio' => $segunda->copy()->setTime(9, 30)->format('Y-m-d\TH:i'),
            'status' => 'agendado',
            'origem' => 'WhatsApp',
            'servicos' => [
                $ctx['servico60']->id => ['servico_id' => $ctx['servico60']->id],
            ],
        ])->assertSessionHasErrors('data_inicio');

        $this->assertSame(4, Agendamento::count());

        // Horário iniciando às 17:00 com previsão de 90 min (18:30 > 18:00) deve ser bloqueado
        $this->post(route('agendamentos.store'), [
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $veic5->id,
            'data_inicio' => $segunda->copy()->setTime(17, 0)->format('Y-m-d\TH:i'),
            'status' => 'agendado',
            'origem' => 'WhatsApp',
            'servicos' => [
                $ctx['servico60']->id => ['servico_id' => $ctx['servico60']->id],
            ],
        ])->assertSessionHasErrors('data_inicio');

        // Horário terminando exatamente até 18:00 (16:00 + 90min = 17:30) passa normalmente
        $this->post(route('agendamentos.store'), [
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $veic5->id,
            'data_inicio' => $segunda->copy()->setTime(16, 0)->format('Y-m-d\TH:i'),
            'status' => 'agendado',
            'origem' => 'WhatsApp',
            'servicos' => [
                $ctx['servico60']->id => ['servico_id' => $ctx['servico60']->id],
            ],
        ])->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_cancelamento_com_os_aberta_estorna_estoque_mesmo_com_produto_inativado_e_bloqueia_remocao_dupla(): void
    {
        $ctx = $this->criarContextoOficina();
        $this->actingAs($ctx['dono']);

        $veiculo = Veiculo::create([
            'cliente_id' => $ctx['cliente']->id,
            'placa' => 'EST1A22',
            'marca' => 'Honda',
            'modelo' => 'Civic',
        ]);

        $produto = Produto::create([
            'empresa_id' => $ctx['empresa']->id,
            'nome' => 'Selante Nano',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 500,
            'estoque_minimo' => 100,
            'custo_unitario' => 100.00,
            'ativo' => true,
        ]);

        $inicio = Carbon::now()->next(Carbon::TUESDAY)->setTime(10, 0);
        $agendamento = Agendamento::create([
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->copy()->addMinutes(90),
            'status' => 'confirmado',
            'origem' => 'WhatsApp',
        ]);
        $agendamento->agendamentosServicos()->create([
            'servico_id' => $ctx['servico60']->id,
            'valor_servico' => 150.00,
            'tempo_estimado_minutos' => 60,
        ]);

        $os = app(AbrirOrdemServicoService::class)->abrir($agendamento->id);
        $tarefa = $os->tarefas()->first();

        $tp = app(MovimentarEstoqueService::class)->registrarConsumoEmTarefa($tarefa, $produto, 50.000, $ctx['dono']);
        $this->assertEquals(450.000, (float) $produto->fresh()->quantidade_estoque);

        // Remoção manual devolve e repetição não duplica
        $this->delete(route('tarefas.remover-produto', [$tarefa->id, $tp->id]))
            ->assertSessionHas('success');
        $this->assertEquals(500.000, (float) $produto->fresh()->quantidade_estoque);

        $this->delete(route('tarefas.remover-produto', [$tarefa->id, $tp->id]))
            ->assertNotFound();
        $this->assertEquals(500.000, (float) $produto->fresh()->quantidade_estoque);

        // Novo consumo seguido de inativação do produto antes de cancelar agendamento
        app(MovimentarEstoqueService::class)->registrarConsumoEmTarefa($tarefa, $produto, 80.000, $ctx['dono']);
        $this->assertEquals(420.000, (float) $produto->fresh()->quantidade_estoque);
        $produto->update(['ativo' => false]);

        $this->patch(route('agendamentos.cancelar', $agendamento->id))
            ->assertSessionHas('success');

        $this->assertSame('cancelado', $agendamento->fresh()->status);
        $this->assertSame('cancelada', $os->fresh()->status);
        $this->assertEquals(500.000, (float) $produto->fresh()->quantidade_estoque);
        $this->assertSame(0, TarefaProduto::where('tarefa_id', $tarefa->id)->count());
    }

    public function test_precisao_decimal_media_ponderada_ajuste_zero_e_conversao_unidades_no_estoque(): void
    {
        $ctx = $this->criarContextoOficina();
        $this->actingAs($ctx['dono']);

        $produto = Produto::create([
            'empresa_id' => $ctx['empresa']->id,
            'nome' => 'Cera Precisão',
            'unidade_medida' => 'L',
            'volume_unitario' => 0.500,
            'quantidade_estoque' => 0.300,
            'estoque_minimo' => 0.100,
            'custo_unitario' => 50.00, // R$ 100/L
            'ativo' => true,
        ]);

        $estoqueService = app(MovimentarEstoqueService::class);
        $estoqueService->movimentar($produto, 'saida', 0.100, $ctx['dono'], 'Saída 0.1');
        $estoqueService->movimentar($produto, 'saida', 0.200, $ctx['dono'], 'Saída 0.2');
        $this->assertEquals(0.000, (float) $produto->fresh()->quantidade_estoque);

        // Entrada em unidades: 4 unidades * 0.500 L = +2.000 L
        $this->post(route('estoque.movimentar'), [
            'produto_id' => $produto->id,
            'tipo_movimentacao' => 'entrada',
            'modo_quantidade' => 'unidades',
            'quantidade' => 4,
            'motivo' => 'Reposição frascos',
        ])->assertSessionHas('success');
        $this->assertEquals(2.000, (float) $produto->fresh()->quantidade_estoque);

        // Média ponderada de custo ao consumir em tarefa após reajuste de custo
        $veiculo = Veiculo::create([
            'cliente_id' => $ctx['cliente']->id,
            'placa' => 'DEC1A23',
            'marca' => 'Ford',
            'modelo' => 'Focus',
        ]);
        $inicio = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(11, 0);
        $ag = Agendamento::create([
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->copy()->addMinutes(90),
            'status' => 'confirmado',
            'origem' => 'WhatsApp',
        ]);
        $ag->agendamentosServicos()->create([
            'servico_id' => $ctx['servico60']->id,
            'valor_servico' => 150.00,
            'tempo_estimado_minutos' => 60,
        ]);
        $os = app(AbrirOrdemServicoService::class)->abrir($ag->id);
        $tarefa = $os->tarefas()->first();

        // 0.100 L a 100.0000/L
        $tp = $estoqueService->registrarConsumoEmTarefa($tarefa, $produto, 0.100, $ctx['dono']);
        // Atualiza custo da embalagem para 100.00 -> 200.0000/L
        $produto->update(['custo_unitario' => 100.00]);
        // +0.100 L a 200.0000/L -> média esperada = 150.0000/L
        $tp = $estoqueService->registrarConsumoEmTarefa($tarefa, $produto, 0.100, $ctx['dono']);
        $this->assertEquals(150.0000, (float) $tp->custo_unitario);

        // Ajuste direto para saldo 0 via EstoqueController
        $this->post(route('estoque.movimentar'), [
            'produto_id' => $produto->id,
            'tipo_movimentacao' => 'ajuste',
            'modo_quantidade' => 'volume',
            'quantidade' => 0,
            'motivo' => 'Zeragem de inventário',
        ])->assertSessionHas('success');
        $this->assertEquals(0.000, (float) $produto->fresh()->quantidade_estoque);
    }

    public function test_relatorios_excluem_os_canceladas_duracao_menor_60s_margem_negativa_e_utf8(): void
    {
        $ctx = $this->criarContextoOficina();
        $this->actingAs($ctx['dono']);

        // Despesa manual R$ 100, receita 0 -> margem -100.0%
        LancamentoFinanceiro::create([
            'empresa_id' => $ctx['empresa']->id,
            'usuario_id' => $ctx['dono']->id,
            'tipo' => 'despesa',
            'categoria' => 'Energia',
            'descricao' => 'Conta de luz',
            'valor' => 100.00,
            'data_lancamento' => now()->toDateString(),
        ]);

        // OS cancelada não deve entrar nas métricas
        $veicCancel = Veiculo::create([
            'cliente_id' => $ctx['cliente']->id,
            'placa' => 'CAN1A11',
            'marca' => 'Fiat',
            'modelo' => 'Argo',
        ]);
        $inicio = now()->subDay()->setTime(10, 0);
        $agCancel = Agendamento::create([
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $veicCancel->id,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->copy()->addMinutes(90),
            'status' => 'cancelado',
            'origem' => 'WhatsApp',
        ]);
        $agCancel->agendamentosServicos()->create([
            'servico_id' => $ctx['servico60']->id,
            'valor_servico' => 150.00,
            'tempo_estimado_minutos' => 60,
        ]);
        $osCancel = OrdemServico::create([
            'agendamento_id' => $agCancel->id,
            'numero' => 'OS-CANCEL-001',
            'data_inicio' => now(),
            'status' => 'cancelada',
        ]);
        Tarefa::create([
            'ordem_servico_id' => $osCancel->id,
            'agendamento_servico_id' => $agCancel->agendamentosServicos()->first()->id,
            'funcionario_id' => $ctx['funcionario']->id,
            'status' => 'pendente',
        ]);

        $response = $this->get(route('relatorios.index', ['periodo' => 'mes']));
        $response->assertOk();
        $kpis = $response->viewData('kpis');
        $funcs = $response->viewData('funcionarios');

        $this->assertEquals(-100.0, $kpis['margem_percent']);
        $this->assertSame(0, $kpis['total_os']);
        $this->assertSame('ÁK', $funcs->first()['iniciais']);
        $this->assertSame(0, $funcs->first()['tarefas_atribuidas']);

        // Tarefa criada no mês anterior e concluída hoje em 15s (deve dar 1m e contar neste mês)
        $veicRapida = Veiculo::create([
            'cliente_id' => $ctx['cliente']->id,
            'placa' => 'RAP1A22',
            'marca' => 'Jeep',
            'modelo' => 'Compass',
        ]);
        $agRapida = Agendamento::create([
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $veicRapida->id,
            'data_inicio' => now()->setTime(11, 0),
            'data_fim' => now()->setTime(12, 30),
            'status' => 'confirmado',
            'origem' => 'WhatsApp',
        ]);
        $agRapida->agendamentosServicos()->create([
            'servico_id' => $ctx['servico60']->id,
            'valor_servico' => 150.00,
            'tempo_estimado_minutos' => 60,
        ]);
        $osRapida = app(AbrirOrdemServicoService::class)->abrir($agRapida->id);
        $tarefaRapida = $osRapida->tarefas()->first();

        app(IniciarTarefaService::class)->iniciar($tarefaRapida, $ctx['funcionario']);
        $tarefaRapida->forceFill([
            'created_at' => now()->subMonthNoOverflow(),
            'data_inicio' => now()->subSeconds(15),
        ])->save();
        app(ConcluirTarefaService::class)->concluir($tarefaRapida, $ctx['funcionario'], $ctx['dono']);

        $respMes = $this->get(route('relatorios.index', ['periodo' => 'mes']));
        $kpisMes = $respMes->viewData('kpis');
        $this->assertSame('1m', $kpisMes['tempo_medio_formatado']);
        $this->assertSame(1, $kpisMes['tarefas_executadas']);
    }

    public function test_query_params_em_array_nao_geram_erro_500(): void
    {
        $ctx = $this->criarContextoOficina();
        $this->actingAs($ctx['dono']);

        $this->get(route('clientes.index', ['busca' => ['abc'], 'acesso' => ['ativo']]))->assertOk();
        $this->get(route('veiculos.index', ['busca' => ['abc']]))->assertOk();
        $this->get(route('servicos.index', ['busca' => ['abc'], 'status' => ['ativo']]))->assertOk();
        $this->get(route('produtos.index', ['busca' => ['abc'], 'alerta' => ['baixo']]))->assertOk();
        $this->get(route('estoque.index', ['busca' => ['abc'], 'tab' => ['movimentacoes']]))->assertOk();
        $this->get(route('agendamentos.index', ['busca' => ['abc'], 'status' => ['agendado']]))->assertOk();
        $this->get(route('ordens-servico.index', ['busca' => ['abc'], 'status' => ['aberta']]))->assertOk();
        $this->get(route('relatorios.index', ['periodo' => ['mes'], 'tab' => ['operacional']]))->assertOk();

        auth()->logout();
        $this->post(route('password.email'), ['email' => ['invalid@array.test']])
            ->assertSessionHasErrors('email');
    }

    public function test_soft_delete_libera_cnpj_cpf_e_remove_pessoa_orfa_sem_vinculos(): void
    {
        $ctx = $this->criarContextoOficina();
        $this->actingAs($ctx['dono']);

        // Soft delete de empresa libera CNPJ para recadastro
        $empTemp = Empresa::create([
            'dono_id' => $ctx['dono']->id,
            'nome_fantasia' => 'Temp Car',
            'razao_social' => 'Temp Car LTDA',
            'cnpj' => '27.865.757/0001-02',
            'whatsapp' => '(11) 91111-1111',
            'email' => 'temp1@krdetail.test',
            'ativo' => true,
        ]);
        $empTemp->delete();

        $this->assertNull(Empresa::withTrashed()->find($empTemp->id)->cnpj);

        $empNova = Empresa::create([
            'dono_id' => $ctx['dono']->id,
            'nome_fantasia' => 'Nova Temp Car',
            'razao_social' => 'Nova Temp Car LTDA',
            'cnpj' => '27.865.757/0001-02',
            'whatsapp' => '(11) 92222-2222',
            'email' => 'temp2@krdetail.test',
            'ativo' => true,
        ]);
        $this->assertNotNull($empNova->id);

        // Exclusão de cliente sem veículos/agendamentos/notificações limpa Pessoa órfã e libera CPF
        $clienteDescartavel = Cliente::create([
            'empresa_id' => $ctx['empresa']->id,
            'nome' => 'Cliente Temporário',
            'cpf_cnpj' => '52998224725',
            'telefone' => '(11) 97777-8888',
            'email' => 'temp.cli@krdetail.test',
        ]);
        $pessoaId = $clienteDescartavel->pessoa_id;

        $this->delete(route('clientes.destroy', $clienteDescartavel->id))
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseMissing('clientes', ['id' => $clienteDescartavel->id]);
        $pessoaSoftDeleted = Pessoa::withTrashed()->find($pessoaId);
        $this->assertNotNull($pessoaSoftDeleted?->deleted_at);
        $this->assertNull($pessoaSoftDeleted?->cpf_cnpj);

        // CPF liberado para novo cadastro
        $novoCli = Cliente::create([
            'empresa_id' => $ctx['empresa']->id,
            'nome' => 'Cliente Novo Mesmo CPF',
            'cpf_cnpj' => '52998224725',
            'telefone' => '(11) 96666-7777',
            'email' => 'novo.cli@krdetail.test',
        ]);
        $this->assertNotNull($novoCli->id);
    }
}
