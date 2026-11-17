<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendamentoTest extends TestCase
{
    use RefreshDatabase;

    private function criarCenarioBasico(): array
    {
        $empresa = Empresa::first() ?? Empresa::create([
            'nome_fantasia' => 'Kraus Detail Teste',
            'razao_social' => 'Kraus Detail Teste LTDA',
            'whatsapp' => '(11) 99999-0000',
            'email' => 'teste@krausdetail.com.br',
            'ativo' => true,
        ]);

        $dono = User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Dono Teste',
            'email' => 'dono.agendamento@krdetail.test',
            'password' => '12345678',
            'telefone' => '(11) 98888-7777',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Carlos Almeida',
            'telefone' => '(11) 97777-6666',
            'email' => 'carlos@cliente.test',
        ]);

        $veiculo = Veiculo::create([
            'cliente_id' => $cliente->id,
            'placa' => 'ABC1D23',
            'marca' => 'Honda',
            'modelo' => 'Civic',
            'ano' => 2023,
            'cor' => 'Preto',
        ]);

        $servico60 = Servico::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Lavagem Técnica',
            'preco_base' => 150.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        $servico90 = Servico::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Higienização Interna',
            'preco_base' => 300.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ]);

        return compact('empresa', 'dono', 'cliente', 'veiculo', 'servico60', 'servico90');
    }

    public function test_calcula_automaticamente_data_fim_com_gargalo_de_30_minutos(): void
    {
        $dados = $this->criarCenarioBasico();
        $inicio = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(9, 0, 0);

        $response = $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $inicio->format('Y-m-d\TH:i'),
            'status' => 'agendado',
            'origem' => 'Presencial',
            'servicos' => [
                ['servico_id' => $dados['servico60']->id],
                ['servico_id' => $dados['servico90']->id],
            ],
        ]);

        $response->assertRedirect();

        $agendamento = Agendamento::first();
        $this->assertNotNull($agendamento);
        $this->assertEquals($inicio->format('Y-m-d H:i'), $agendamento->data_inicio->format('Y-m-d H:i'));
        // 60 + 90 + 30 min gargalo = 180 min -> 09:00 + 3h = 12:00
        $this->assertEquals($inicio->copy()->addMinutes(180)->format('Y-m-d H:i'), $agendamento->data_fim->format('Y-m-d H:i'));
        $this->assertNull($agendamento->data_conclusao);
    }

    public function test_bloqueia_conflito_de_horario_para_o_mesmo_veiculo(): void
    {
        $dados = $this->criarCenarioBasico();
        $inicio = Carbon::now()->next(Carbon::THURSDAY)->setTime(9, 0, 0);

        // Primeiro agendamento: 09:00 às 10:30 (60 + 30 gargalo)
        $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $inicio->format('Y-m-d\TH:i'),
            'servicos' => [
                ['servico_id' => $dados['servico60']->id],
            ],
        ])->assertRedirect();

        // Tentativa sobreposta às 10:00 para o mesmo veículo
        $response = $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $inicio->copy()->addHour()->format('Y-m-d\TH:i'),
            'servicos' => [
                ['servico_id' => $dados['servico60']->id],
            ],
        ]);

        $response->assertSessionHasErrors('data_inicio');
        $this->assertSame(1, Agendamento::count());
    }

    public function test_respeita_limite_de_capacidade_simultanea_de_4_boxes(): void
    {
        $dados = $this->criarCenarioBasico();
        $inicio = Carbon::now()->next(Carbon::FRIDAY)->setTime(10, 0, 0);

        for ($i = 1; $i <= 4; $i++) {
            $veiculoBox = Veiculo::create([
                'cliente_id' => $dados['cliente']->id,
                'placa' => "BOX000{$i}",
                'marca' => 'VW',
                'modelo' => "Golf {$i}",
            ]);

            $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
                'cliente_id' => $dados['cliente']->id,
                'veiculo_id' => $veiculoBox->id,
                'data_inicio' => $inicio->format('Y-m-d\TH:i'),
                'servicos' => [
                    ['servico_id' => $dados['servico60']->id],
                ],
            ])->assertRedirect();
        }

        // 5º veículo no mesmo horário excede a capacidade de 4 boxes
        $veiculoExcedente = Veiculo::create([
            'cliente_id' => $dados['cliente']->id,
            'placa' => 'BOX0005',
            'marca' => 'Audi',
            'modelo' => 'A3',
        ]);

        $response = $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $veiculoExcedente->id,
            'data_inicio' => $inicio->format('Y-m-d\TH:i'),
            'servicos' => [
                ['servico_id' => $dados['servico60']->id],
            ],
        ]);

        $response->assertSessionHasErrors('data_inicio');
        $this->assertSame(4, Agendamento::count());
    }

    public function test_rejeita_agendamento_no_domingo_ou_fora_do_expediente(): void
    {
        $dados = $this->criarCenarioBasico();

        // Domingo às 10:00
        $domingo = Carbon::now()->next(Carbon::SUNDAY)->setTime(10, 0, 0);
        $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $domingo->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $dados['servico60']->id]],
        ])->assertSessionHasErrors('data_inicio');

        // Segunda às 07:00 (antes das 08:00)
        $cedao = Carbon::now()->next(Carbon::MONDAY)->setTime(7, 0, 0);
        $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $cedao->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $dados['servico60']->id]],
        ])->assertSessionHasErrors('data_inicio');

        // Segunda às 19:00 (após as 18:00)
        $tardao = Carbon::now()->next(Carbon::MONDAY)->setTime(19, 0, 0);
        $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $tardao->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $dados['servico60']->id]],
        ])->assertSessionHasErrors('data_inicio');

        $this->assertSame(0, Agendamento::count());
    }

    public function test_valida_rn01_veiculo_do_cliente_e_previne_servicos_duplicados(): void
    {
        $dados = $this->criarCenarioBasico();
        $outroCliente = Cliente::create([
            'empresa_id' => $dados['empresa']->id,
            'nome' => 'Outro Cliente',
            'telefone' => '(11) 96666-5555',
        ]);

        $inicio = Carbon::now()->next(Carbon::TUESDAY)->setTime(14, 0, 0);

        // Veículo de $dados['cliente'] associado a $outroCliente -> deve falhar
        $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $outroCliente->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $inicio->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $dados['servico60']->id]],
        ])->assertSessionHasErrors('veiculo_id');

        // Serviço duplicado -> deve falhar
        $this->actingAs($dados['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $dados['cliente']->id,
            'veiculo_id' => $dados['veiculo']->id,
            'data_inicio' => $inicio->format('Y-m-d\TH:i'),
            'servicos' => [
                ['servico_id' => $dados['servico60']->id],
                ['servico_id' => $dados['servico60']->id],
            ],
        ])->assertSessionHasErrors();
    }

    public function test_cancelamento_pelo_cliente_exige_antecedencia_minima_de_1_hora(): void
    {
        $dados = $this->criarCenarioBasico();

        $userCliente = User::create([
            'empresa_id' => $dados['empresa']->id,
            'name' => 'Cliente Portal',
            'email' => 'portal.cliente@krdetail.test',
            'password' => '12345678',
            'telefone' => '(11) 95555-4444',
            'tipo_usuario' => 'cliente',
            'ativo' => true,
        ]);
        $clientePortal = $userCliente->ensureClienteProfile();

        $veiculoPortal = Veiculo::create([
            'cliente_id' => $clientePortal->id,
            'placa' => 'PRT1A23',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
        ]);

        // Agendamento daqui a 30 minutos (< 1 hora) -> não deve cancelar
        $agCurto = Agendamento::create([
            'cliente_id' => $clientePortal->id,
            'veiculo_id' => $veiculoPortal->id,
            'data_inicio' => Carbon::now()->addMinutes(30),
            'data_fim' => Carbon::now()->addMinutes(120),
            'status' => 'agendado',
            'origem' => 'Site',
        ]);

        $resCurto = $this->actingAs($userCliente)->patch(route('agendamentos.cancelar', $agCurto));
        $resCurto->assertSessionHas('error');
        $this->assertSame('agendado', $agCurto->fresh()->status);

        // Agendamento daqui a 2 horas (> 1 hora) -> deve permitir cancelar
        $agPermitido = Agendamento::create([
            'cliente_id' => $clientePortal->id,
            'veiculo_id' => $veiculoPortal->id,
            'data_inicio' => Carbon::now()->addHours(2),
            'data_fim' => Carbon::now()->addHours(4),
            'status' => 'agendado',
            'origem' => 'Site',
        ]);

        $resPermitido = $this->actingAs($userCliente)->patch(route('agendamentos.cancelar', $agPermitido));
        $resPermitido->assertSessionHas('success');
        $this->assertSame('cancelado', $agPermitido->fresh()->status);
    }
}
