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

class CalendarioJanelasAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    private function criarEsteticaCompleta(string $sufixo): array
    {
        $dono = User::create([
            'name' => "Dono {$sufixo}",
            'email' => strtolower("dono.{$sufixo}@agenda.test"),
            'password' => 'SenhaForte123',
            'telefone' => '(11) 91111-0000',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => "Estética {$sufixo}",
            'razao_social' => "Estética {$sufixo} LTDA",
            'whatsapp' => '(11) 91111-0000',
            'email' => strtolower("contato.{$sufixo}@agenda.test"),
            'ativo' => true,
        ]);
        $dono->update(['empresa_id' => $empresa->id]);

        $clienteUser = User::create([
            'name' => "Cliente {$sufixo}",
            'email' => strtolower("cliente.{$sufixo}@agenda.test"),
            'password' => 'SenhaForte123',
            'telefone' => '(11) 92222-0000',
            'tipo_usuario' => 'cliente',
            'empresa_id' => $empresa->id,
            'ativo' => true,
        ]);
        $cliente = $clienteUser->ensureClienteProfile();

        $veiculo = Veiculo::withoutGlobalScopes()->create([
            'cliente_id' => $cliente->id,
            'placa' => strtoupper(substr($sufixo . 'AAA', 0, 3)) . '1D23',
            'marca' => 'BMW',
            'modelo' => '320i',
            'ano' => 2024,
        ]);

        $outroCliente = Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Terceiro {$sufixo}",
            'telefone' => '(11) 93333-0000',
            'email' => strtolower("terceiro.{$sufixo}@agenda.test"),
        ]);

        $veiculoTerceiro = Veiculo::withoutGlobalScopes()->create([
            'cliente_id' => $outroCliente->id,
            'placa' => strtoupper(substr($sufixo . 'BBB', 0, 3)) . '9Z99',
            'marca' => 'Audi',
            'modelo' => 'A3',
            'ano' => 2023,
        ]);

        $servicoRapido = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Lavagem 60m {$sufixo}",
            'preco_base' => 150.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        $servicoLongo = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nome' => "Polimento 180m {$sufixo}",
            'preco_base' => 650.00,
            'tempo_estimado_minutos' => 180,
            'ativo' => true,
        ]);

        return compact(
            'dono',
            'empresa',
            'clienteUser',
            'cliente',
            'veiculo',
            'outroCliente',
            'veiculoTerceiro',
            'servicoRapido',
            'servicoLongo'
        );
    }

    /**
     * @return array<int, User>
     */
    private function adicionarFuncionariosAtivos(int $empresaId, int $quantidade, string $prefixo): array
    {
        $criados = [];
        for ($i = 1; $i <= $quantidade; $i++) {
            $criados[] = User::create([
                'empresa_id' => $empresaId,
                'name' => "Equipe {$prefixo} {$i}",
                'email' => strtolower("equipe.{$prefixo}.{$i}@agenda.test"),
                'password' => 'SenhaForte123',
                'telefone' => "(11) 96666-000{$i}",
                'tipo_usuario' => 'funcionario',
                'ativo' => true,
            ]);
        }

        return $criados;
    }

    public function test_servicos_exigem_multiplos_de_30_minutos_na_criacao_e_edicao(): void
    {
        $ctx = $this->criarEsteticaCompleta('Srv30');

        foreach ([15, 25, 45, 75] as $tempoInvalido) {
            $this->actingAs($ctx['dono'])->post(route('servicos.store'), [
                'nome' => "Serviço {$tempoInvalido}m",
                'preco_base' => 100.00,
                'tempo_estimado_minutos' => $tempoInvalido,
                'ativo' => true,
            ])->assertSessionHasErrors('tempo_estimado_minutos');
        }

        $this->actingAs($ctx['dono'])->post(route('servicos.store'), [
            'nome' => 'Higienização Express',
            'preco_base' => 120.00,
            'tempo_estimado_minutos' => 30,
            'ativo' => true,
        ])->assertRedirect(route('servicos.index'))->assertSessionHas('success');

        $servicoCriado = Servico::withoutGlobalScopes()
            ->where('empresa_id', $ctx['empresa']->id)
            ->where('nome', 'Higienização Express')
            ->firstOrFail();

        $this->actingAs($ctx['dono'])->put(route('servicos.update', $servicoCriado), [
            'nome' => 'Higienização Express',
            'preco_base' => 140.00,
            'tempo_estimado_minutos' => 45,
            'ativo' => true,
        ])->assertSessionHasErrors('tempo_estimado_minutos');

        $this->actingAs($ctx['dono'])->put(route('servicos.update', $servicoCriado), [
            'nome' => 'Higienização Express',
            'preco_base' => 140.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ])->assertRedirect(route('servicos.index'))->assertSessionHas('success');

        $this->assertSame(90, (int) $servicoCriado->fresh()->tempo_estimado_minutos);
    }

    public function test_feed_eventos_respeita_isolamento_multi_tenant_e_filtro_admin(): void
    {
        $alfa = $this->criarEsteticaCompleta('Alf');
        $beta = $this->criarEsteticaCompleta('Bet');

        $admin = User::create([
            'name' => 'Admin Calendario',
            'email' => 'admin.calendario@agenda.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 90000-0001',
            'tipo_usuario' => 'administrador',
            'ativo' => true,
        ]);

        $quarta = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(9, 0);

        $agAlfa = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $alfa['empresa']->id,
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $alfa['veiculo']->id,
            'data_inicio' => $quarta->copy(),
            'data_fim' => $quarta->copy()->addMinutes(90),
            'status' => 'confirmado',
            'origem' => 'Site',
        ]);

        $agBeta = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $beta['empresa']->id,
            'cliente_id' => $beta['cliente']->id,
            'veiculo_id' => $beta['veiculo']->id,
            'data_inicio' => $quarta->copy()->addHours(2),
            'data_fim' => $quarta->copy()->addHours(3)->addMinutes(30),
            'status' => 'agendado',
            'origem' => 'WhatsApp',
        ]);

        $paramsRange = [
            'start' => $quarta->copy()->startOfDay()->format('Y-m-d\TH:i:s'),
            'end' => $quarta->copy()->endOfDay()->format('Y-m-d\TH:i:s'),
        ];

        $respAlfa = $this->actingAs($alfa['dono'])
            ->getJson(route('agendamentos.eventos', $paramsRange));
        $respAlfa->assertOk();
        $idsAlfa = collect($respAlfa->json())->pluck('id')->all();
        $this->assertContains((string) $agAlfa->id, $idsAlfa);
        $this->assertNotContains((string) $agBeta->id, $idsAlfa);

        $respAdminFiltrado = $this->actingAs($admin)
            ->getJson(route('agendamentos.eventos', array_merge($paramsRange, ['empresa_id' => $beta['empresa']->id])));
        $respAdminFiltrado->assertOk();
        $idsAdmin = collect($respAdminFiltrado->json())->pluck('id')->all();
        $this->assertContains((string) $agBeta->id, $idsAdmin);
        $this->assertNotContains((string) $agAlfa->id, $idsAdmin);
    }

    public function test_feed_eventos_para_cliente_oculta_terceiros_e_sinaliza_lotacao_anonima(): void
    {
        $ctx = $this->criarEsteticaCompleta('Cli');
        $this->adicionarFuncionariosAtivos($ctx['empresa']->id, 3, 'cli4b');
        $quinta = Carbon::now()->next(Carbon::THURSDAY)->setTime(10, 0);

        $meuAg = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $ctx['veiculo']->id,
            'data_inicio' => $quinta->copy(),
            'data_fim' => $quinta->copy()->addMinutes(90),
            'status' => 'agendado',
            'origem' => 'Site',
        ]);

        // Ocupa mais 3 boxes de outros clientes no mesmo intervalo (10:00 às 11:30 => 4/4 boxes ocupados)
        for ($i = 1; $i <= 3; $i++) {
            $cliExtra = Cliente::withoutGlobalScopes()->create([
                'empresa_id' => $ctx['empresa']->id,
                'nome' => "Segredo Cliente {$i}",
                'telefone' => "(11) 98888-000{$i}",
                'email' => "segredo{$i}@agenda.test",
            ]);
            $veiExtra = Veiculo::withoutGlobalScopes()->create([
                'cliente_id' => $cliExtra->id,
                'placa' => "SEC000{$i}",
                'marca' => 'Porsche',
                'modelo' => '911',
                'ano' => 2025,
            ]);
            Agendamento::withoutGlobalScopes()->create([
                'empresa_id' => $ctx['empresa']->id,
                'cliente_id' => $cliExtra->id,
                'veiculo_id' => $veiExtra->id,
                'data_inicio' => $quinta->copy(),
                'data_fim' => $quinta->copy()->addMinutes(90),
                'status' => 'confirmado',
                'origem' => 'Balcão',
            ]);
        }

        $resp = $this->actingAs($ctx['clienteUser'])->getJson(route('agendamentos.eventos', [
            'start' => $quinta->copy()->startOfDay()->format('Y-m-d\TH:i:s'),
            'end' => $quinta->copy()->endOfDay()->format('Y-m-d\TH:i:s'),
        ]));

        $resp->assertOk();
        $jsonStr = $resp->getContent();
        $this->assertStringNotContainsString('Segredo Cliente', $jsonStr);
        $this->assertStringNotContainsString('SEC000', $jsonStr);

        $eventos = collect($resp->json());
        $this->assertTrue($eventos->contains(fn ($ev) => ($ev['id'] ?? null) === (string) $meuAg->id));

        $fundoLotado = $eventos->first(fn ($ev) => ($ev['display'] ?? null) === 'background');
        $this->assertNotNull($fundoLotado);
        $this->assertSame('Horário lotado', $fundoLotado['title']);
        $this->assertSame($quinta->format('Y-m-d\T10:00:00'), $fundoLotado['start']);
        $this->assertSame($quinta->format('Y-m-d\T11:30:00'), $fundoLotado['end']);
    }

    public function test_janelas_disponiveis_respeita_domingo_lotacao_veiculo_pernoite_e_privacidade(): void
    {
        $ctx = $this->criarEsteticaCompleta('Jan');
        $this->adicionarFuncionariosAtivos($ctx['empresa']->id, 3, 'jan4b');
        $proximaSegunda = Carbon::now()->next(Carbon::MONDAY)->startOfDay();
        $domingoSeguinte = $proximaSegunda->copy()->addDays(6); // Domingo

        // Lota 09:00..10:30 na segunda (4 boxes)
        for ($i = 1; $i <= 4; $i++) {
            $cli = Cliente::withoutGlobalScopes()->create([
                'empresa_id' => $ctx['empresa']->id,
                'nome' => "Box Lotado {$i}",
                'telefone' => "(11) 97777-000{$i}",
                'email' => "box{$i}@agenda.test",
            ]);
            $vei = Veiculo::withoutGlobalScopes()->create([
                'cliente_id' => $cli->id,
                'placa' => "LOT000{$i}",
                'marca' => 'VW',
                'modelo' => 'Golf',
                'ano' => 2022,
            ]);
            Agendamento::withoutGlobalScopes()->create([
                'empresa_id' => $ctx['empresa']->id,
                'cliente_id' => $cli->id,
                'veiculo_id' => $vei->id,
                'data_inicio' => $proximaSegunda->copy()->setTime(9, 0),
                'data_fim' => $proximaSegunda->copy()->setTime(10, 30),
                'status' => 'confirmado',
            ]);
        }

        // Veículo do cliente tem agendamento ativo às 14:00..15:30 na mesma segunda
        Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['cliente']->id,
            'veiculo_id' => $ctx['veiculo']->id,
            'data_inicio' => $proximaSegunda->copy()->setTime(14, 0),
            'data_fim' => $proximaSegunda->copy()->setTime(15, 30),
            'status' => 'agendado',
        ]);

        // Consulta como Cliente para o próprio veículo com serviço de 180 min (+30 gargalo = 210 min)
        $respCli = $this->actingAs($ctx['clienteUser'])->getJson(route('agendamentos.janelas-disponiveis', [
            'data_base' => $proximaSegunda->format('Y-m-d'),
            'dias' => 7,
            'servicos' => [$ctx['servicoLongo']->id],
            'veiculo_id' => $ctx['veiculo']->id,
            'deixar_veiculo' => 0,
        ]));

        $respCli->assertOk();
        $respCli->assertJsonPath('duracao_servicos_minutos', 180);
        $respCli->assertJsonPath('duracao_total_minutos', 210);

        $dias = collect($respCli->json('dias'));
        $segundaObj = $dias->firstWhere('data', $proximaSegunda->format('Y-m-d'));
        $domingoObj = $dias->firstWhere('data', $domingoSeguinte->format('Y-m-d'));

        $this->assertTrue($domingoObj['fechado']);
        $this->assertSame('fechado', $domingoObj['status']);

        $slotsSegunda = collect($segundaObj['slots']);
        $this->assertCount(20, $slotsSegunda); // 08:00 até 17:30 de 30 em 30 min

        // 08:00 + 210min = 11:30 => sobrepõe 09:00..10:30 (lotado 4/4 boxes!)
        $slot0800 = $slotsSegunda->firstWhere('hora', '08:00');
        $this->assertFalse($slot0800['disponivel']);
        $this->assertSame('lotado', $slot0800['motivo']);

        // 10:30 + 210min = 14:00 => não colide com 09:00..10:30 nem com veículo 14:00..15:30!
        $slot1030 = $slotsSegunda->firstWhere('hora', '10:30');
        $this->assertTrue($slot1030['disponivel']);
        $this->assertArrayNotHasKey('boxes_livres', $slot1030); // Privacidade do cliente

        // 11:00 + 210min = 14:30 => colide com veículo do cliente (14:00..15:30)!
        $slot1100 = $slotsSegunda->firstWhere('hora', '11:00');
        $this->assertFalse($slot1100['disponivel']);
        $this->assertSame('conflito_veiculo', $slot1100['motivo']);

        // 16:00 + 210min = 19:30 => ultrapassa 18:00 sem pernoite
        $slot1600 = $slotsSegunda->firstWhere('hora', '16:00');
        $this->assertFalse($slot1600['disponivel']);
        $this->assertSame('ultrapassa_expediente', $slot1600['motivo']);

        // Com deixar_veiculo=1 e perfil Dono: 16:00 fica disponível (término terça 09:30) e exibe boxes_livres
        $respDonoPernoite = $this->actingAs($ctx['dono'])->getJson(route('agendamentos.janelas-disponiveis', [
            'data_base' => $proximaSegunda->format('Y-m-d'),
            'dias' => 7,
            'servicos' => [$ctx['servicoLongo']->id],
            'veiculo_id' => $ctx['veiculo']->id,
            'deixar_veiculo' => 1,
        ]));
        $respDonoPernoite->assertOk();
        $segundaDono = collect($respDonoPernoite->json('dias'))->firstWhere('data', $proximaSegunda->format('Y-m-d'));
        $slot1600Pernoite = collect($segundaDono['slots'])->firstWhere('hora', '16:00');
        $this->assertTrue($slot1600Pernoite['disponivel']);
        $this->assertTrue($slot1600Pernoite['pernoite']);
        $this->assertSame(4, $slot1600Pernoite['boxes_livres']);

        // Cliente não consegue sondar conflito de veículo de terceiro passando veiculo_id alheio
        $terca = $proximaSegunda->copy()->addDay();
        Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $ctx['empresa']->id,
            'cliente_id' => $ctx['outroCliente']->id,
            'veiculo_id' => $ctx['veiculoTerceiro']->id,
            'data_inicio' => $terca->copy()->setTime(10, 0),
            'data_fim' => $terca->copy()->setTime(11, 30),
            'status' => 'confirmado',
        ]);
        $respSondagem = $this->actingAs($ctx['clienteUser'])->getJson(route('agendamentos.janelas-disponiveis', [
            'data_base' => $terca->format('Y-m-d'),
            'dias' => 1,
            'servicos' => [$ctx['servicoRapido']->id],
            'veiculo_id' => $ctx['veiculoTerceiro']->id,
        ]));
        $respSondagem->assertOk();
        $slotTerca10 = collect($respSondagem->json('dias.0.slots'))->firstWhere('hora', '10:00');
        $this->assertTrue($slotTerca10['disponivel']);
        $this->assertNotSame('conflito_veiculo', $slotTerca10['motivo']);
    }

    public function test_views_index_e_create_renderizam_alternador_e_painel_de_janelas(): void
    {
        $ctx = $this->criarEsteticaCompleta('View');
        $alvo = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 30);

        $this->actingAs($ctx['clienteUser'])
            ->get(route('agendamentos.index', ['view' => 'calendario']))
            ->assertOk()
            ->assertSee('id="btn-view-calendario"', false)
            ->assertSee('id="agendamentos-calendario-root"', false);

        $this->actingAs($ctx['clienteUser'])
            ->get(route('agendamentos.create', ['data_inicio' => $alvo->format('Y-m-d\TH:i')]))
            ->assertOk()
            ->assertSee('id="janelas-disponiveis-root"', false)
            ->assertSee($alvo->format('Y-m-d\TH:i'), false)
            ->assertDontSee('step="1800"', false);
    }

    public function test_equipe_pode_confirmar_agendamento_rapidamente_com_isolamento_e_bloqueio_cliente(): void
    {
        $alfa = $this->criarEsteticaCompleta('CfA');
        $beta = $this->criarEsteticaCompleta('CfB');

        $funcionarioAlfa = User::create([
            'name' => 'Func ConfAlf',
            'email' => 'func.conf@agenda.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 95555-0000',
            'tipo_usuario' => 'funcionario',
            'empresa_id' => $alfa['empresa']->id,
            'ativo' => true,
        ]);

        $quinta = Carbon::now()->next(Carbon::THURSDAY)->setTime(9, 0);

        $agAgendado = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $alfa['empresa']->id,
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $alfa['veiculo']->id,
            'data_inicio' => $quinta->copy(),
            'data_fim' => $quinta->copy()->addMinutes(90),
            'status' => 'agendado',
            'origem' => 'Site',
        ]);

        $agCancelado = Agendamento::withoutGlobalScopes()->create([
            'empresa_id' => $alfa['empresa']->id,
            'cliente_id' => $alfa['outroCliente']->id,
            'veiculo_id' => $alfa['veiculoTerceiro']->id,
            'data_inicio' => $quinta->copy()->addHours(2),
            'data_fim' => $quinta->copy()->addHours(3)->addMinutes(30),
            'status' => 'cancelado',
            'origem' => 'Balcão',
        ]);

        // 1. Cliente bloqueado (redirect no web, 403 em JSON) e não vê o botão de confirmar
        $this->actingAs($alfa['clienteUser'])
            ->get(route('agendamentos.show', $agAgendado))
            ->assertOk()
            ->assertDontSee('Confirmar Agendamento');

        $this->actingAs($alfa['clienteUser'])
            ->patch(route('agendamentos.confirmar', $agAgendado))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
        $this->actingAs($alfa['clienteUser'])
            ->patchJson(route('agendamentos.confirmar', $agAgendado))
            ->assertForbidden();
        $this->assertSame('agendado', $agAgendado->fresh()->status);

        // 2. Dono da outra empresa recebe 404 (multi-tenant)
        $this->actingAs($beta['dono'])
            ->patch(route('agendamentos.confirmar', $agAgendado))
            ->assertNotFound();
        $this->assertSame('agendado', $agAgendado->fresh()->status);

        // 3. Dono vê o botão quando agendado; Funcionário consegue confirmar com 1 clique
        $this->actingAs($alfa['dono'])
            ->get(route('agendamentos.show', $agAgendado))
            ->assertOk()
            ->assertSee('Confirmar Agendamento');

        $this->actingAs($funcionarioAlfa)
            ->from(route('agendamentos.show', $agAgendado))
            ->patch(route('agendamentos.confirmar', $agAgendado))
            ->assertRedirect(route('agendamentos.show', $agAgendado))
            ->assertSessionHas('success');

        $this->assertSame('confirmado', $agAgendado->fresh()->status);

        // 4. Após confirmado, botão não aparece mais e nova tentativa falha em segurança
        $this->actingAs($alfa['dono'])
            ->get(route('agendamentos.show', $agAgendado))
            ->assertOk()
            ->assertDontSee('Confirmar Agendamento');

        $this->actingAs($alfa['dono'])
            ->from(route('agendamentos.show', $agAgendado))
            ->patch(route('agendamentos.confirmar', $agAgendado))
            ->assertRedirect(route('agendamentos.show', $agAgendado))
            ->assertSessionHas('error');

        $this->actingAs($alfa['dono'])
            ->from(route('agendamentos.show', $agCancelado))
            ->patch(route('agendamentos.confirmar', $agCancelado))
            ->assertRedirect(route('agendamentos.show', $agCancelado))
            ->assertSessionHas('error');

        $this->assertSame('cancelado', $agCancelado->fresh()->status);
    }

    public function test_capacidade_simultanea_limita_pela_equipe_ativa_incluindo_dono_ate_teto_de_4_boxes(): void
    {
        $alfa = $this->criarEsteticaCompleta('CpA');
        $beta = $this->criarEsteticaCompleta('CpB');
        $terca09 = Carbon::now()->next(Carbon::TUESDAY)->setTime(9, 0);

        $veiculos = [];
        for ($i = 1; $i <= 5; $i++) {
            $veiculos[$i] = Veiculo::withoutGlobalScopes()->create([
                'cliente_id' => $alfa['cliente']->id,
                'placa' => "EQP000{$i}",
                'marca' => 'Honda',
                'modelo' => "Civic {$i}",
                'ano' => 2024,
            ]);
        }

        // 1. Apenas dono ativo (0 funcionários): capacidade = 1
        $respAntes = $this->actingAs($alfa['dono'])->getJson(route('agendamentos.janelas-disponiveis', [
            'data_base' => $terca09->format('Y-m-d'),
            'dias' => 1,
            'servicos' => [$alfa['servicoRapido']->id],
        ]));
        $respAntes->assertOk()
            ->assertJsonPath('equipe_ativa', 1)
            ->assertJsonPath('capacidade_simultanea', 1);
        $slot09Antes = collect($respAntes->json('dias.0.slots'))->firstWhere('hora', '09:00');
        $this->assertTrue($slot09Antes['disponivel']);
        $this->assertSame('1/1 vaga', $slot09Antes['vagas_label']);

        $this->actingAs($alfa['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $veiculos[1]->id,
            'data_inicio' => $terca09->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $alfa['servicoRapido']->id]],
        ])->assertRedirect();

        $respDepois1 = $this->actingAs($alfa['dono'])->getJson(route('agendamentos.janelas-disponiveis', [
            'data_base' => $terca09->format('Y-m-d'),
            'dias' => 1,
            'servicos' => [$alfa['servicoRapido']->id],
        ]));
        $slot09Depois1 = collect($respDepois1->json('dias.0.slots'))->firstWhere('hora', '09:00');
        $this->assertFalse($slot09Depois1['disponivel']);
        $this->assertSame('lotado', $slot09Depois1['motivo']);

        $this->actingAs($alfa['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $veiculos[2]->id,
            'data_inicio' => $terca09->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $alfa['servicoRapido']->id]],
        ])->assertSessionHasErrors('data_inicio');

        // 2. Dono + 2 funcionários ativos + 1 inativo => equipe ativa = 3 (permite 3, bloqueia 4º)
        $this->adicionarFuncionariosAtivos($alfa['empresa']->id, 2, 'capa3');
        User::create([
            'empresa_id' => $alfa['empresa']->id,
            'name' => 'Inativo CapA',
            'email' => 'inativo.capa@agenda.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 96666-9999',
            'tipo_usuario' => 'funcionario',
            'ativo' => false,
        ]);

        foreach ([2, 3] as $idx) {
            $this->actingAs($alfa['dono'])->post(route('agendamentos.store'), [
                'cliente_id' => $alfa['cliente']->id,
                'veiculo_id' => $veiculos[$idx]->id,
                'data_inicio' => $terca09->format('Y-m-d\TH:i'),
                'servicos' => [['servico_id' => $alfa['servicoRapido']->id]],
            ])->assertRedirect();
        }

        $this->actingAs($alfa['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $veiculos[4]->id,
            'data_inicio' => $terca09->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $alfa['servicoRapido']->id]],
        ])->assertSessionHasErrors('data_inicio');

        // Cliente vê fundo "Horário lotado" com 3/3 ocupados e janelas não vazam equipe_ativa/boxes_livres
        $respEventosCli = $this->actingAs($alfa['clienteUser'])->getJson(route('agendamentos.eventos', [
            'start' => $terca09->copy()->startOfDay()->format('Y-m-d\TH:i:s'),
            'end' => $terca09->copy()->endOfDay()->format('Y-m-d\TH:i:s'),
        ]));
        $respEventosCli->assertOk();
        $fundoLotado = collect($respEventosCli->json())->first(fn ($ev) => ($ev['display'] ?? null) === 'background');
        $this->assertNotNull($fundoLotado);
        $this->assertSame('Horário lotado', $fundoLotado['title']);

        $respJanelasCli = $this->actingAs($alfa['clienteUser'])->getJson(route('agendamentos.janelas-disponiveis', [
            'data_base' => $terca09->format('Y-m-d'),
            'dias' => 1,
            'servicos' => [$alfa['servicoRapido']->id],
        ]));
        $respJanelasCli->assertOk()
            ->assertJsonMissingPath('equipe_ativa')
            ->assertJsonMissingPath('capacidade_simultanea');
        $slot09Cli = collect($respJanelasCli->json('dias.0.slots'))->firstWhere('hora', '09:00');
        $this->assertFalse($slot09Cli['disponivel']);
        $this->assertSame('lotado', $slot09Cli['motivo']);
        $this->assertArrayNotHasKey('boxes_livres', $slot09Cli);

        // 3. Dono + 5 funcionários ativos (6 pessoas) => teto físico de 4 boxes (permite 4º, bloqueia 5º)
        $this->adicionarFuncionariosAtivos($alfa['empresa']->id, 3, 'capa6');

        $respComSeis = $this->actingAs($alfa['dono'])->getJson(route('agendamentos.janelas-disponiveis', [
            'data_base' => $terca09->format('Y-m-d'),
            'dias' => 1,
            'servicos' => [$alfa['servicoRapido']->id],
        ]));
        $respComSeis->assertOk()
            ->assertJsonPath('equipe_ativa', 6)
            ->assertJsonPath('capacidade_simultanea', 4);

        $this->actingAs($alfa['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $veiculos[4]->id,
            'data_inicio' => $terca09->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $alfa['servicoRapido']->id]],
        ])->assertRedirect();

        $this->actingAs($alfa['dono'])->post(route('agendamentos.store'), [
            'cliente_id' => $alfa['cliente']->id,
            'veiculo_id' => $veiculos[5]->id,
            'data_inicio' => $terca09->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $alfa['servicoRapido']->id]],
        ])->assertSessionHasErrors('data_inicio');

        // 4. Dono com 2 empresas mantém sua contagem na empresa Alfa mesmo ao alternar empresa_id ativa para Beta
        $beta['empresa']->update(['dono_id' => $alfa['dono']->id]);
        $alfa['dono']->update(['empresa_id' => $beta['empresa']->id]);
        $this->assertSame(6, $alfa['empresa']->fresh()->contarEquipeOperacionalAtiva());
        $this->assertSame(4, $alfa['empresa']->fresh()->calcularCapacidadeSimultanea());
    }
}
