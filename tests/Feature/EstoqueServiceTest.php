<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\AbrirOrdemServicoService;
use App\Services\MovimentarEstoqueService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_consumo_de_produto_em_tarefa_com_rastreabilidade_e_abatimento_de_saldo(): void
    {
        $empresa = Empresa::first() ?? Empresa::create([
            'nome_fantasia' => 'Kraus Detail Estoque',
            'razao_social' => 'Kraus Detail Estoque LTDA',
            'whatsapp' => '(11) 99999-0002',
            'email' => 'estoque@krausdetail.test',
            'ativo' => true,
        ]);

        $usuario = User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador Estoque',
            'email' => 'op.estoque@krdetail.test',
            'password' => '12345678',
            'telefone' => '(11) 98888-3333',
            'tipo_usuario' => 'funcionario',
            'ativo' => true,
        ]);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Mariana Rocha',
            'telefone' => '(11) 97777-3333',
        ]);

        $veiculo = Veiculo::create([
            'cliente_id' => $cliente->id,
            'placa' => 'EST1A23',
            'marca' => 'Porsche',
            'modelo' => 'Macan',
        ]);

        $servico = Servico::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Vitrificação Cerâmica',
            'preco_base' => 1500.00,
            'tempo_estimado_minutos' => 240,
            'ativo' => true,
        ]);

        $agendamento = Agendamento::create([
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => Carbon::now()->next(Carbon::WEDNESDAY)->setTime(9, 0),
            'data_fim' => Carbon::now()->next(Carbon::WEDNESDAY)->setTime(13, 30),
            'status' => 'agendado',
        ]);

        AgendamentoServico::create([
            'agendamento_id' => $agendamento->id,
            'servico_id' => $servico->id,
            'valor_servico' => 1500.00,
            'tempo_estimado_minutos' => 240,
        ]);

        $os = app(AbrirOrdemServicoService::class)->abrir($agendamento->id);
        $tarefa = $os->tarefas->first();

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Coating Cerâmico 9H',
            'unidade_medida' => 'ml',
            'volume_unitario' => 50,
            'quantidade_estoque' => 100.000,
            'estoque_minimo' => 20.000,
            'custo_unitario' => 250.00,
            'ativo' => true,
        ]);

        $service = app(MovimentarEstoqueService::class);
        $tarefaProduto = $service->registrarConsumoEmTarefa($tarefa, $produto, 30.000, $usuario);

        $produto->refresh();
        $this->assertEquals(70.000, (float) $produto->quantidade_estoque);

        $mov = MovimentacaoEstoque::where('tarefa_produto_id', $tarefaProduto->id)->first();
        $this->assertNotNull($mov);
        $this->assertSame('saida', $mov->tipo_movimentacao);
        $this->assertEquals(100.000, (float) $mov->quantidade_anterior);
        $this->assertEquals(70.000, (float) $mov->quantidade_posterior);
        $this->assertSame($usuario->id, $mov->usuario_id);
    }

    public function test_bloqueia_saida_com_saldo_insuficiente_no_estoque(): void
    {
        $empresa = Empresa::first() ?? Empresa::create([
            'nome_fantasia' => 'Kraus Detail Estoque',
            'razao_social' => 'Kraus Detail Estoque LTDA',
            'whatsapp' => '(11) 99999-0002',
            'email' => 'estoque2@krausdetail.test',
            'ativo' => true,
        ]);

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cera de Carnaúba',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 150.000,
            'estoque_minimo' => 50.000,
            'custo_unitario' => 80.00,
            'ativo' => true,
        ]);

        $service = app(MovimentarEstoqueService::class);

        try {
            $service->movimentar($produto, 'saida', 200.000);
            $this->fail('Era esperado lançar DomainException por estoque insuficiente.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Estoque insuficiente', $e->getMessage());
        }

        $this->assertEquals(150.000, (float) $produto->fresh()->quantidade_estoque);
        $this->assertSame(0, MovimentacaoEstoque::count());
    }
}
