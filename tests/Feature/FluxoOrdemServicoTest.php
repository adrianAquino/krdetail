<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\AgendamentoServico;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\LancamentoFinanceiro;
use App\Models\OrdemServico;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\AbrirOrdemServicoService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FluxoOrdemServicoTest extends TestCase
{
    use RefreshDatabase;

    private function criarAgendamentoComDoisServicos(): array
    {
        $empresa = Empresa::first() ?? Empresa::create([
            'nome_fantasia' => 'Kraus Detail OS',
            'razao_social' => 'Kraus Detail OS LTDA',
            'whatsapp' => '(11) 99999-0001',
            'email' => 'os@krausdetail.test',
            'ativo' => true,
        ]);

        $userFuncionario = User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador Lucas',
            'email' => 'lucas.op@krdetail.test',
            'password' => '12345678',
            'telefone' => '(11) 98888-1111',
            'tipo_usuario' => 'funcionario',
            'ativo' => true,
        ]);

        $funcionario = Funcionario::withoutGlobalScopes()->where('user_id', $userFuncionario->id)->firstOrFail();

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Juliana Pires',
            'telefone' => '(11) 97777-2222',
        ]);

        $veiculo = Veiculo::create([
            'cliente_id' => $cliente->id,
            'placa' => 'OSV9X88',
            'marca' => 'BMW',
            'modelo' => '320i',
        ]);

        $s1 = Servico::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Lavagem Premium',
            'preco_base' => 200.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        $s2 = Servico::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Polimento Comercial',
            'preco_base' => 550.00,
            'tempo_estimado_minutos' => 180,
            'ativo' => true,
        ]);

        $inicio = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(9, 0, 0);
        // 60 + 180 + 30 gargalo = 270 min
        $previsaoOriginal = $inicio->copy()->addMinutes(270);

        $agendamento = Agendamento::create([
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'data_inicio' => $inicio,
            'data_fim' => $previsaoOriginal,
            'data_conclusao' => null,
            'status' => 'agendado',
            'origem' => 'Presencial',
        ]);

        AgendamentoServico::create([
            'agendamento_id' => $agendamento->id,
            'servico_id' => $s1->id,
            'valor_servico' => 200.00,
            'tempo_estimado_minutos' => 60,
        ]);

        AgendamentoServico::create([
            'agendamento_id' => $agendamento->id,
            'servico_id' => $s2->id,
            'valor_servico' => 550.00,
            'tempo_estimado_minutos' => 180,
        ]);

        return compact('empresa', 'userFuncionario', 'funcionario', 'agendamento', 'previsaoOriginal');
    }

    public function test_abertura_de_os_gera_tarefas_pendentes_e_impede_duplicidade(): void
    {
        $dados = $this->criarAgendamentoComDoisServicos();
        $service = app(AbrirOrdemServicoService::class);

        $os = $service->abrir($dados['agendamento']->id);

        $this->assertSame('aberta', $os->status);
        $this->assertNotNull($os->data_inicio);
        $this->assertNull($os->data_fim);
        $this->assertCount(2, $os->tarefas);
        foreach ($os->tarefas as $tarefa) {
            $this->assertSame('pendente', $tarefa->status);
            $this->assertNull($tarefa->data_inicio);
            $this->assertNull($tarefa->data_conclusao);
        }

        $this->expectException(DomainException::class);
        $service->abrir($dados['agendamento']->id);
    }

    public function test_bloqueia_inicio_de_tarefa_sem_funcionario_atribuido(): void
    {
        $dados = $this->criarAgendamentoComDoisServicos();
        $os = app(AbrirOrdemServicoService::class)->abrir($dados['agendamento']->id);
        $tarefa1 = $os->tarefas->first();

        $response = $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.iniciar', $tarefa1));

        $response->assertSessionHas('error');
        $tarefa1->refresh();
        $this->assertSame('pendente', $tarefa1->status);
        $this->assertNull($tarefa1->data_inicio);
    }

    public function test_fluxo_completo_de_tarefas_registra_horarios_gera_financeiro_e_fecha_os_e_agendamento(): void
    {
        $dados = $this->criarAgendamentoComDoisServicos();
        $os = app(AbrirOrdemServicoService::class)->abrir($dados['agendamento']->id);
        [$tarefa1, $tarefa2] = [$os->tarefas[0], $os->tarefas[1]];

        // Atribui e inicia a 1ª tarefa
        $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.atribuir', $tarefa1), ['funcionario_id' => $dados['funcionario']->id])
            ->assertSessionHas('success');

        $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.iniciar', $tarefa1))
            ->assertSessionHas('success');

        $tarefa1->refresh();
        $os->refresh();
        $agendamento = $dados['agendamento']->fresh();

        $this->assertSame('em_andamento', $tarefa1->status);
        $this->assertNotNull($tarefa1->data_inicio);
        $this->assertNull($tarefa1->data_conclusao);
        $this->assertSame('em_andamento', $os->status);
        $this->assertSame('em_andamento', $agendamento->status);

        // Conclui a 1ª tarefa -> gera receita mas OS continua em_andamento
        $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.concluir', $tarefa1))
            ->assertSessionHas('success');

        $tarefa1->refresh();
        $os->refresh();

        $this->assertSame('concluida', $tarefa1->status);
        $this->assertNotNull($tarefa1->data_conclusao);
        $this->assertSame('em_andamento', $os->status);
        $this->assertNull($os->data_fim);

        $lancamento1 = LancamentoFinanceiro::where('tarefa_id', $tarefa1->id)->first();
        $this->assertNotNull($lancamento1);
        $this->assertSame('receita', $lancamento1->tipo);
        $this->assertEquals(200.00, (float) $lancamento1->valor);

        // Tentar concluir a 2ª tarefa sem iniciá-la antes deve falhar
        $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.atribuir', $tarefa2), ['funcionario_id' => $dados['funcionario']->id])
            ->assertSessionHas('success');

        $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.concluir', $tarefa2))
            ->assertSessionHas('error');

        $this->assertSame('pendente', $tarefa2->fresh()->status);

        // Inicia e conclui a 2ª (última) tarefa -> fecha OS e Agendamento
        $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.iniciar', $tarefa2))
            ->assertSessionHas('success');

        $this->actingAs($dados['userFuncionario'])
            ->patch(route('tarefas.concluir', $tarefa2))
            ->assertSessionHas('success');

        $tarefa2->refresh();
        $os->refresh();
        $agendamento->refresh();

        $this->assertSame('concluida', $tarefa2->status);
        $this->assertNotNull($tarefa2->data_inicio);
        $this->assertNotNull($tarefa2->data_conclusao);

        $this->assertSame('concluida', $os->status);
        $this->assertNotNull($os->data_fim);

        $this->assertSame('concluido', $agendamento->status);
        $this->assertNotNull($agendamento->data_conclusao);
        // Previsão estimada original (data_fim) preservada intacta
        $this->assertEquals(
            $dados['previsaoOriginal']->format('Y-m-d H:i'),
            $agendamento->data_fim->format('Y-m-d H:i')
        );

        $this->assertSame(2, LancamentoFinanceiro::count());
        $this->assertEquals(750.00, (float) LancamentoFinanceiro::sum('valor'));
    }
}
