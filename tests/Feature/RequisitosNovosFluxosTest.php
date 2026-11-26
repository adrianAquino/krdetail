<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\ClienteEmpresaHistorico;
use App\Models\Empresa;
use App\Models\Servico;
use App\Models\User;
use App\Models\Veiculo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RequisitosNovosFluxosTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_sem_pre_cadastro_nao_vincula_estetica_e_vincula_quando_dono_cadastra_cliente(): void
    {
        $donoA = User::create([
            'name' => 'Dono A',
            'email' => 'dono.a@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 90000-0001',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresaA = Empresa::create([
            'dono_id' => $donoA->id,
            'nome_fantasia' => 'Estética Alfa Detailing',
            'razao_social' => 'Alfa Detailing LTDA',
            'whatsapp' => '(11) 90000-0001',
            'email' => 'contato@alfa.test',
            'ativo' => true,
        ]);
        $donoA->update(['empresa_id' => $empresaA->id]);

        $servicoA = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $empresaA->id,
            'nome' => 'Lavagem Completa',
            'preco_base' => 120.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ]);

        // 1. Auto-cadastro sem pré-cadastro em nenhuma estética
        $this->post(route('register.post'), [
            'name' => 'Lucas AutoCadastro',
            'email' => 'lucas.novo@cliente.test',
            'telefone' => '(11) 98888-1234',
            'cpf_cnpj' => '529.982.247-25',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ])->assertRedirect(route('dashboard'));

        $userLucas = User::where('email', 'lucas.novo@cliente.test')->firstOrFail();
        $this->assertNull($userLucas->empresa_id);
        $this->assertFalse($userLucas->possuiEsteticaVinculada());

        // Perfil deve exibir que não está vinculado a nenhuma estética no momento
        $this->actingAs($userLucas)
            ->get(route('configuracoes.index', ['tab' => 'perfil']))
            ->assertOk()
            ->assertSee('Nenhuma estética vinculada no momento')
            ->assertSee('Sem vínculo ativo no momento');

        // Pode cadastrar veículo próprio
        $this->actingAs($userLucas)->post(route('veiculos.store'), [
            'placa' => 'LUC1A23',
            'marca' => 'Volkswagen',
            'modelo' => 'Nivus',
            'ano' => 2024,
        ])->assertRedirect();

        $veiculoLucas = Veiculo::withoutGlobalScopes()->where('placa', 'LUC1A23')->firstOrFail();

        // Não pode acessar criação nem solicitar novos agendamentos sem estética vinculada
        $this->actingAs($userLucas)->get(route('agendamentos.create'))
            ->assertRedirect(route('agendamentos.index'))
            ->assertSessionHas('error');

        $inicio = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 0);
        $this->actingAs($userLucas)->post(route('agendamentos.store'), [
            'veiculo_id' => $veiculoLucas->id,
            'data_inicio' => $inicio->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servicoA->id]],
        ])->assertRedirect(route('agendamentos.index'))
            ->assertSessionHas('error');
        $this->assertSame(0, Agendamento::withoutGlobalScopes()->count());

        // 2. Dono da Estética A cadastra cliente com dados coincidentes -> vincula automaticamente
        $this->actingAs($donoA)->post(route('clientes.store'), [
            'nome' => 'Lucas AutoCadastro',
            'email' => 'lucas.novo@cliente.test',
            'telefone' => '(11) 98888-1234',
            'cpf_cnpj' => '52998224725',
        ])->assertRedirect();

        $userLucas->refresh();
        $this->assertSame($empresaA->id, (int) $userLucas->empresa_id);
        $this->assertTrue($userLucas->possuiEsteticaVinculada());

        $this->assertDatabaseHas('cliente_empresa_historicos', [
            'user_id' => $userLucas->id,
            'empresa_id' => $empresaA->id,
            'desvinculado_em' => null,
        ]);

        // Agora consegue criar agendamento na Estética A
        $this->actingAs($userLucas)->get(route('agendamentos.create'))->assertOk();
        $this->actingAs($userLucas)->post(route('agendamentos.store'), [
            'veiculo_id' => $veiculoLucas->id,
            'data_inicio' => $inicio->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servicoA->id]],
        ])->assertRedirect();

        $agendamentoA = Agendamento::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($empresaA->id, (int) $agendamentoA->empresa_id);

        // Conclui atendimento para simular histórico e desvincula da Estética A
        $agendamentoA->update(['status' => 'concluido', 'data_conclusao' => now()]);
        $clienteLucas = Cliente::withoutGlobalScopes()->where('user_id', $userLucas->id)->firstOrFail();

        $this->actingAs($donoA)->delete(route('clientes.destroy', $clienteLucas->id))
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('success');

        $userLucas->refresh();
        $this->assertTrue($userLucas->ativo);
        $this->assertNull($userLucas->empresa_id);
        $this->assertFalse($userLucas->possuiEsteticaVinculada());

        // Histórico mantém registro encerrado na estética A e antigo serviço visível ao cliente
        $hist = ClienteEmpresaHistorico::where('user_id', $userLucas->id)->firstOrFail();
        $this->assertNotNull($hist->desvinculado_em);

        $this->actingAs($userLucas)->get(route('agendamentos.index'))
            ->assertOk()
            ->assertSee('Estética Alfa Detailing');

        $this->actingAs($userLucas)->get(route('configuracoes.index', ['tab' => 'perfil']))
            ->assertOk()
            ->assertSee('Nenhuma estética vinculada no momento')
            ->assertSee('Estética Alfa Detailing')
            ->assertSee('Vínculo Anterior');

        // Sem vínculo novamente, não pode pedir novo agendamento
        $this->actingAs($userLucas)->get(route('agendamentos.create'))
            ->assertRedirect(route('agendamentos.index'))
            ->assertSessionHas('error');
    }

    public function test_restricoes_criacao_admin_e_dono_e_alternancia_entre_esteticas_do_dono(): void
    {
        $admin = User::create([
            'name' => 'Admin Sistema',
            'email' => 'admin.sys@krdetail.test',
            'password' => 'SenhaForte123',
            'tipo_usuario' => 'administrador',
            'ativo' => true,
        ]);

        $dono = User::create([
            'name' => 'Dono Multi Estética',
            'email' => 'dono.multi@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 91111-1111',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $esteticaA = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => 'Estética Alpha',
            'razao_social' => 'Alpha LTDA',
            'whatsapp' => '(11) 91111-1111',
            'email' => 'alpha@krdetail.test',
            'ativo' => true,
        ]);

        $esteticaB = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => 'Estética Beta',
            'razao_social' => 'Beta LTDA',
            'whatsapp' => '(11) 92222-2222',
            'email' => 'beta@krdetail.test',
            'ativo' => true,
        ]);

        $dono->update(['empresa_id' => $esteticaA->id]);

        // Admin NÃO pode cadastrar outro administrador via sistema
        $this->actingAs($admin)->post(route('configuracoes.usuarios.store'), [
            'name' => 'Segundo Admin',
            'email' => 'admin2@krdetail.test',
            'tipo_usuario' => 'administrador',
            'password' => 'SenhaForte123',
        ])->assertSessionHasErrors('tipo_usuario');
        $this->assertDatabaseMissing('users', ['email' => 'admin2@krdetail.test']);

        // Admin pode cadastrar Dono sem selecionar empresa_id (dono criará sua estética depois)
        $this->actingAs($admin)->post(route('configuracoes.usuarios.store'), [
            'name' => 'Dono Sem Empresa Inicial',
            'email' => 'dono.novo@krdetail.test',
            'tipo_usuario' => 'dono',
            'empresa_id' => '',
            'password' => 'SenhaForte123',
        ])->assertRedirect(route('configuracoes.index', ['tab' => 'equipe']));

        $donoNovo = User::where('email', 'dono.novo@krdetail.test')->firstOrFail();
        $this->assertSame('dono', $donoNovo->tipo_usuario);
        $this->assertNull($donoNovo->empresa_id, 'O Dono cadastrado sem empresa não deve ter empresa_id vinculada automaticamente');

        $this->actingAs($donoNovo)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sem Estética')
            ->assertDontSee('Kraus Detail');

        $this->actingAs($donoNovo)->get(route('configuracoes.index', ['tab' => 'equipe']))
            ->assertOk()
            ->assertSee('Dono Sem Empresa Inicial')
            ->assertSee('dono.novo@krdetail.test')
            ->assertSee('Sem empresa vinculada')
            ->assertSee('Nenhuma empresa vinculada')
            ->assertDontSee('Kraus Detail')
            ->assertDontSee($dono->email);

        // Admin pode cadastrar Dono, Funcionário e Cliente; Dono NÃO pode cadastrar Admin nem outro Dono
        $this->actingAs($dono)->post(route('configuracoes.usuarios.store'), [
            'name' => 'Outro Dono',
            'email' => 'outro.dono@krdetail.test',
            'tipo_usuario' => 'dono',
            'password' => 'SenhaForte123',
        ])->assertSessionHasErrors('tipo_usuario');
        $this->assertDatabaseMissing('users', ['email' => 'outro.dono@krdetail.test']);

        $this->actingAs($dono)->post(route('configuracoes.usuarios.store'), [
            'name' => 'Novo Funcionário Alpha',
            'email' => 'func.alpha@krdetail.test',
            'tipo_usuario' => 'funcionario',
            'password' => 'SenhaForte123',
        ])->assertRedirect(route('configuracoes.index', ['tab' => 'equipe']));
        $funcAlpha = User::where('email', 'func.alpha@krdetail.test')->firstOrFail();

        // Dono também não pode promover funcionário para Dono ou Admin
        $this->actingAs($dono)->put(route('configuracoes.usuarios.update', $funcAlpha), [
            'name' => $funcAlpha->name,
            'email' => $funcAlpha->email,
            'tipo_usuario' => 'dono',
        ])->assertSessionHasErrors('tipo_usuario');
        $this->assertSame('funcionario', $funcAlpha->fresh()->tipo_usuario);

        // Cria clientes exclusivos na Estética Alpha e na Estética Beta (sem usuário logado sobrescrever empresa_id)
        
        Auth::logout();
        Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $esteticaA->id,
            'nome' => 'Cliente Exclusivo Alpha',
            'telefone' => '(11) 93333-1111',
        ]);
        Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $esteticaB->id,
            'nome' => 'Cliente Exclusivo Beta',
            'telefone' => '(11) 93333-2222',
        ]);

        // Na Estética A, vê apenas dados da Alpha
        $this->actingAs($dono)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Cliente Exclusivo Alpha')
            ->assertDontSee('Cliente Exclusivo Beta');

        // Troca ativa para a Estética Beta -> vê apenas dados da Beta
        $this->actingAs($dono)->post(route('configuracoes.empresas.selecionar', $esteticaB))
            ->assertSessionHas('success');

        $dono->refresh();
        $this->assertSame($esteticaB->id, (int) $dono->empresa_id);

        $this->actingAs($dono)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Cliente Exclusivo Beta')
            ->assertDontSee('Cliente Exclusivo Alpha');

        // Novo cliente cadastrado após alternar é vinculado à Estética Beta
        $this->actingAs($dono)->post(route('clientes.store'), [
            'nome' => 'Cliente Novo Direto Beta',
            'telefone' => '(11) 94444-3333',
        ])->assertRedirect();

        $this->assertDatabaseHas('clientes', [
            'nome' => 'Cliente Novo Direto Beta',
            'empresa_id' => $esteticaB->id,
        ]);
    }

    public function test_modal_cadastro_veiculo_ajax_colisao_veiculo_boxes_e_pernoite(): void
    {
        $dono = User::create([
            'name' => 'Dono Agenda',
            'email' => 'dono.agenda@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 90000-0011',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => 'Kraus Agenda',
            'razao_social' => 'Kraus Agenda LTDA',
            'whatsapp' => '(11) 90000-0011',
            'email' => 'agenda@krdetail.test',
            'ativo' => true,
        ]);
        $dono->update(['empresa_id' => $empresa->id]);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Mariana Rocha',
            'telefone' => '(11) 95555-6666',
        ]);

        $servico240 = Servico::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Vitrificação Completa',
            'preco_base' => 1200.00,
            'tempo_estimado_minutos' => 240, // +30 gargalo = 270min (4h30)
            'ativo' => true,
        ]);

        // View de criação exibe campo datetime-local limpo (sem modal de calendário) e aviso do modal de veículo
        $this->actingAs($dono)->get(route('agendamentos.create'))
            ->assertOk()
            ->assertSee('type="datetime-local"', false)
            ->assertDontSee('modal-calendario-agenda', false)
            ->assertDontSee('abrirCalendarioAgenda', false)
            ->assertSee('Este veículo será cadastrado e atribuído diretamente ao cliente selecionado')
            ->assertSee('Deixar o veículo na estética');

        // Endpoint exclusivo do calendário foi removido
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('agendamentos.disponibilidade'));

        // Cadastro rápido de veículo via AJAX (JSON 201)
        $respVeiculo = $this->actingAs($dono)->postJson(route('veiculos.store'), [
            'cliente_id' => $cliente->id,
            'placa' => 'MOD1A23',
            'marca' => 'Porsche',
            'modelo' => '911 Carrera',
            'ano' => 2024,
        ]);
        $respVeiculo->assertCreated()
            ->assertJsonPath('veiculo.placa', 'MOD1A23')
            ->assertJsonPath('veiculo.cliente_id', $cliente->id);

        $veiculoId = $respVeiculo->json('veiculo.id');

        // Pernoite: às 16:00 de sexta, 270 min = 120 min sexta (16:00->18:00) + 150 min sábado (08:00->10:30)
        $sexta16 = Carbon::now()->next(Carbon::FRIDAY)->setTime(16, 0, 0);
        $sabado1030 = $sexta16->copy()->addDay()->setTime(10, 30, 0);

        // Sem deixar_veiculo = true, deve bloquear por ultrapassar as 18:00
        $this->actingAs($dono)->post(route('agendamentos.store'), [
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculoId,
            'data_inicio' => $sexta16->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servico240->id]],
        ])->assertSessionHasErrors('data_inicio');

        // Com deixar_veiculo = 1, permite e calcula data_fim no próximo dia útil (sábado 10:30)
        $this->actingAs($dono)->post(route('agendamentos.store'), [
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculoId,
            'data_inicio' => $sexta16->format('Y-m-d\TH:i'),
            'deixar_veiculo' => '1',
            'servicos' => [['servico_id' => $servico240->id]],
        ])->assertRedirect();

        $ag1 = Agendamento::firstOrFail();
        $this->assertTrue($ag1->deixar_veiculo);
        $this->assertSame($sabado1030->format('Y-m-d H:i'), $ag1->data_fim->format('Y-m-d H:i'));

        // View de edição também exibe apenas o campo datetime-local sem modal de calendário
        $this->actingAs($dono)->get(route('agendamentos.edit', $ag1))
            ->assertOk()
            ->assertSee('type="datetime-local"', false)
            ->assertDontSee('modal-calendario-agenda', false)
            ->assertDontSee('abrirCalendarioAgenda', false);

        // Segundo agendamento no mesmo veículo sobreposto no sábado 09:00 -> deve bloquear no store e no update
        $sabado09 = $sabado1030->copy()->setTime(9, 0, 0);
        $terca10 = Carbon::now()->next(Carbon::TUESDAY)->setTime(10, 0, 0);

        $this->actingAs($dono)->post(route('agendamentos.store'), [
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculoId,
            'data_inicio' => $sabado09->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servico240->id]],
        ])->assertSessionHasErrors('data_inicio');

        // Cria na terça sem conflito, depois tenta editar movendo para sábado 09:00 -> deve bloquear
        $this->actingAs($dono)->post(route('agendamentos.store'), [
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculoId,
            'data_inicio' => $terca10->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servico240->id]],
        ])->assertRedirect();

        $ag2 = Agendamento::where('id', '!=', $ag1->id)->firstOrFail();
        $this->actingAs($dono)->put(route('agendamentos.update', $ag2), [
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculoId,
            'data_inicio' => $sabado09->format('Y-m-d\TH:i'),
            'status' => 'confirmado',
            'servicos' => [['servico_id' => $servico240->id]],
        ])->assertSessionHasErrors('data_inicio');

        // Lotando os 4 boxes em um horário de quarta-feira (10:00 -> 14:30), o 5º agendamento no mesmo horário deve ser bloqueado
        $quarta10 = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 0, 0);
        for ($i = 1; $i <= 4; $i++) {
            $vBox = Veiculo::create([
                'cliente_id' => $cliente->id,
                'placa' => "CAL000{$i}",
                'marca' => 'Audi',
                'modelo' => "A{$i}",
            ]);
            Agendamento::create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente->id,
                'veiculo_id' => $vBox->id,
                'data_inicio' => $quarta10,
                'data_fim' => $quarta10->copy()->addMinutes(270),
                'status' => 'confirmado',
                'origem' => 'WhatsApp',
            ]);
        }

        $veiculoQuintoBox = Veiculo::create([
            'cliente_id' => $cliente->id,
            'placa' => 'CAL0005',
            'marca' => 'Audi',
            'modelo' => 'A5',
        ]);

        $this->actingAs($dono)->post(route('agendamentos.store'), [
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculoQuintoBox->id,
            'data_inicio' => $quarta10->format('Y-m-d\TH:i'),
            'servicos' => [['servico_id' => $servico240->id]],
        ])->assertSessionHasErrors('data_inicio');
    }

    public function test_todos_formularios_com_endereco_renderizam_consulta_viacep(): void
    {
        $dono = User::create([
            'name' => 'Dono ViaCEP',
            'email' => 'dono.viacep@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 90000-0022',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => 'Kraus ViaCEP',
            'razao_social' => 'Kraus ViaCEP LTDA',
            'whatsapp' => '(11) 90000-0022',
            'email' => 'viacep@krdetail.test',
            'ativo' => true,
        ]);
        $dono->update(['empresa_id' => $empresa->id]);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente ViaCEP',
            'telefone' => '(11) 97777-8888',
        ]);

        $clienteUser = User::create([
            'name' => 'Cliente Conta ViaCEP',
            'email' => 'cliente.viacep@krdetail.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 97777-9999',
            'tipo_usuario' => 'cliente',
            'empresa_id' => $empresa->id,
            'ativo' => true,
        ]);

        // Formulários com x-layouts.app
        $this->actingAs($dono)->get(route('clientes.create'))
            ->assertOk()
            ->assertSee('onblur="pesquisacep(this.value);"', false)
            ->assertSee('window.consultarViaCepInput', false)
            ->assertSee('https://viacep.com.br/ws/', false);

        $this->actingAs($dono)->get(route('clientes.edit', $cliente))
            ->assertOk()
            ->assertSee('onblur="pesquisacep(this.value);"', false)
            ->assertSee('window.consultarViaCepInput', false);

        $this->actingAs($dono)->get(route('veiculos.create'))
            ->assertOk()
            ->assertSee('onblur="pesquisacepModal(this.value);"', false)
            ->assertSee('window.consultarViaCepInput', false);

        // Modal Editar Empresa, Modal Nova Empresa e Modal Editar Meu Perfil em configuracoes.index
        $respConfigDono = $this->actingAs($dono)->get(route('configuracoes.index'));
        $respConfigDono->assertOk()
            ->assertSee('window.consultarViaCepInput = async function', false)
            ->assertSee('https://viacep.com.br/ws/', false)
            ->assertSee('id="edit_emp_' . $empresa->id . '_cep"', false)
            ->assertSee('id="nova_emp_cep"', false)
            ->assertSee('id="meu_perfil_cep"', false);

        $htmlConfigDono = $respConfigDono->getContent();
        $this->assertSame(
            3,
            substr_count($htmlConfigDono, 'onblur="window.consultarViaCepInput(this);"'),
            'Os 3 modais com endereço (Editar Empresa, Nova Empresa e Editar Meu Perfil) devem chamar consultarViaCepInput no evento blur.'
        );

        // Cliente também visualiza o modal Editar Meu Perfil com ViaCEP habilitado
        $this->actingAs($clienteUser)->get(route('configuracoes.index'))
            ->assertOk()
            ->assertSee('id="meu_perfil_cep"', false)
            ->assertSee('onblur="window.consultarViaCepInput(this);"', false);
    }
}
