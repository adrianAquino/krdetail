<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BloqueioDuplicidadesTest extends TestCase
{
    use RefreshDatabase;

    private function criarContextoEmpresa(string $sufixo, string $nomeFantasia): array
    {
        $dono = User::create([
            'name' => "Dono {$sufixo}",
            'email' => strtolower("dono.{$sufixo}@duplicidade.test"),
            'password' => 'SenhaForte123',
            'telefone' => '(11) 91111-0001',
            'tipo_usuario' => 'dono',
            'ativo' => true,
        ]);

        $empresa = Empresa::create([
            'dono_id' => $dono->id,
            'nome_fantasia' => $nomeFantasia,
            'razao_social' => "{$nomeFantasia} LTDA",
            'whatsapp' => '(11) 91111-0001',
            'email' => strtolower("{$sufixo}@duplicidade.test"),
            'ativo' => true,
        ]);
        $dono->update(['empresa_id' => $empresa->id]);

        return compact('dono', 'empresa');
    }

    public function test_bloqueia_duplicidade_de_servicos_na_mesma_empresa_e_permite_entre_empresas_distintas(): void
    {
        $alfa = $this->criarContextoEmpresa('AlfaSrv', 'Estética Alfa Serviços');
        $beta = $this->criarContextoEmpresa('BetaSrv', 'Estética Beta Serviços');

        // 1. Cadastra serviço inicial ativo e serviço inativo
        $this->actingAs($alfa['dono'])->post(route('servicos.store'), [
            'nome' => 'Lavagem Técnica',
            'preco_base' => 150.00,
            'tempo_estimado_minutos' => 60,
            'ativo' => true,
        ])->assertRedirect(route('servicos.index'));

        $servicoInativo = Servico::withoutGlobalScopes()->create([
            'empresa_id' => $alfa['empresa']->id,
            'nome' => 'Vitrificação Antiga',
            'preco_base' => 900.00,
            'tempo_estimado_minutos' => 300,
            'ativo' => false,
        ]);

        $servicoAtivo = Servico::withoutGlobalScopes()
            ->where('empresa_id', $alfa['empresa']->id)
            ->where('nome', 'Lavagem Técnica')
            ->firstOrFail();

        // 2. Bloqueia nome exato, variação de caixa/espaços e contra serviço inativo na mesma empresa
        $this->actingAs($alfa['dono'])->post(route('servicos.store'), [
            'nome' => 'Lavagem Técnica',
            'preco_base' => 180.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ])->assertSessionHasErrors('nome');

        $this->actingAs($alfa['dono'])->post(route('servicos.store'), [
            'nome' => '  lavagem   TÉCNICA  ',
            'preco_base' => 180.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ])->assertSessionHasErrors('nome');

        $this->actingAs($alfa['dono'])->post(route('servicos.store'), [
            'nome' => 'vitrificação antiga',
            'preco_base' => 1100.00,
            'tempo_estimado_minutos' => 360,
            'ativo' => true,
        ])->assertSessionHasErrors('nome');

        $this->assertSame(2, Servico::withoutGlobalScopes()->where('empresa_id', $alfa['empresa']->id)->count());

        // 3. Permite editar o próprio serviço mantendo o nome (e bloqueia renomear para outro já existente)
        $this->actingAs($alfa['dono'])->put(route('servicos.update', $servicoAtivo), [
            'nome' => 'Lavagem   Técnica',
            'preco_base' => 170.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ])->assertRedirect(route('servicos.index'))->assertSessionHas('success');
        $this->assertSame('Lavagem Técnica', $servicoAtivo->fresh()->nome);
        $this->assertEquals(170.00, (float) $servicoAtivo->fresh()->preco_base);

        $this->actingAs($alfa['dono'])->put(route('servicos.update', $servicoAtivo), [
            'nome' => $servicoInativo->nome,
            'preco_base' => 170.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ])->assertSessionHasErrors('nome');
        $this->assertSame('Lavagem Técnica', $servicoAtivo->fresh()->nome);

        // 4. Outra empresa pode cadastrar serviço com o mesmo nome normalmente
        $this->actingAs($beta['dono'])->post(route('servicos.store'), [
            'nome' => 'Lavagem Técnica',
            'preco_base' => 190.00,
            'tempo_estimado_minutos' => 90,
            'ativo' => true,
        ])->assertRedirect(route('servicos.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('servicos', [
            'empresa_id' => $beta['empresa']->id,
            'nome' => 'Lavagem Técnica',
        ]);
    }

    public function test_bloqueia_duplicidade_de_produtos_na_mesma_empresa_e_permite_entre_empresas_distintas(): void
    {
        $alfa = $this->criarContextoEmpresa('AlfaPrd', 'Estética Alfa Produtos');
        $beta = $this->criarContextoEmpresa('BetaPrd', 'Estética Beta Produtos');

        $this->actingAs($alfa['dono'])->post(route('produtos.store'), [
            'nome' => 'Shampoo Neutro',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_unidades' => 3,
            'estoque_minimo_unidades' => 1,
            'custo_unitario' => 42.90,
            'ativo' => true,
        ])->assertRedirect();

        $produtoInativo = Produto::withoutGlobalScopes()->create([
            'empresa_id' => $alfa['empresa']->id,
            'nome' => 'Cera Descontinuada',
            'unidade_medida' => 'g',
            'volume_unitario' => 100,
            'quantidade_estoque' => 100,
            'estoque_minimo' => 50,
            'custo_unitario' => 60.00,
            'ativo' => false,
        ]);

        $produtoAtivo = Produto::withoutGlobalScopes()
            ->where('empresa_id', $alfa['empresa']->id)
            ->where('nome', 'Shampoo Neutro')
            ->firstOrFail();

        // Bloqueia exato, variação de maiúsculas/espaços e contra produto inativo
        foreach (['Shampoo Neutro', '  shampoo   NEUTRO  ', 'cera descontinuada'] as $nomeDuplicado) {
            $this->actingAs($alfa['dono'])->post(route('produtos.store'), [
                'nome' => $nomeDuplicado,
                'unidade_medida' => 'ml',
                'volume_unitario' => 500,
                'quantidade_unidades' => 2,
                'estoque_minimo_unidades' => 1,
                'custo_unitario' => 45.00,
                'ativo' => true,
            ])->assertSessionHasErrors('nome');
        }

        $this->assertSame(2, Produto::withoutGlobalScopes()->where('empresa_id', $alfa['empresa']->id)->count());

        // Permite atualizar o próprio produto mantendo o nome; bloqueia colidir com outro produto
        $this->actingAs($alfa['dono'])->put(route('produtos.update', $produtoAtivo), [
            'nome' => 'Shampoo   Neutro',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 1500,
            'estoque_minimo' => 500,
            'custo_unitario' => 49.90,
            'ativo' => true,
        ])->assertRedirect(route('produtos.show', $produtoAtivo))->assertSessionHas('success');
        $this->assertSame('Shampoo Neutro', $produtoAtivo->fresh()->nome);
        $this->assertEquals(49.90, (float) $produtoAtivo->fresh()->custo_unitario);

        $this->actingAs($alfa['dono'])->put(route('produtos.update', $produtoAtivo), [
            'nome' => $produtoInativo->nome,
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_estoque' => 1500,
            'estoque_minimo' => 500,
            'custo_unitario' => 49.90,
            'ativo' => true,
        ])->assertSessionHasErrors('nome');
        $this->assertSame('Shampoo Neutro', $produtoAtivo->fresh()->nome);

        // Outra empresa pode cadastrar produto com o mesmo nome
        $this->actingAs($beta['dono'])->post(route('produtos.store'), [
            'nome' => 'Shampoo Neutro',
            'unidade_medida' => 'ml',
            'volume_unitario' => 500,
            'quantidade_unidades' => 2,
            'estoque_minimo_unidades' => 1,
            'custo_unitario' => 42.90,
            'ativo' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('produtos', [
            'empresa_id' => $beta['empresa']->id,
            'nome' => 'Shampoo Neutro',
        ]);
    }

    public function test_bloqueia_duplicidade_de_clientes_por_email_ou_nome_e_telefone_na_mesma_empresa(): void
    {
        $alfa = $this->criarContextoEmpresa('AlfaCli', 'Estética Alfa Clientes');
        $beta = $this->criarContextoEmpresa('BetaCli', 'Estética Beta Clientes');

        // 1. Cadastra cliente offline (sem conta user e sem CPF)
        $this->actingAs($alfa['dono'])->post(route('clientes.store'), [
            'nome' => 'Carlos Eduardo',
            'email' => 'carlos@cliente.test',
            'telefone' => '(11) 98765-4321',
        ])->assertRedirect();

        $carlos = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $alfa['empresa']->id)
            ->where('email', 'carlos@cliente.test')
            ->firstOrFail();

        // 2. Bloqueia e-mail duplicado mesmo sem marcar criar_usuario e com nome/telefone diferentes
        $this->actingAs($alfa['dono'])->post(route('clientes.store'), [
            'nome' => 'Outro Nome Qualquer',
            'email' => 'CARLOS@CLIENTE.TEST',
            'telefone' => '(11) 91234-9999',
        ])->assertSessionHasErrors('email');

        // 3. Bloqueia mesmo nome + mesmo telefone (ignorando máscara, caixa e espaços extras) sem e-mail/CPF
        $this->actingAs($alfa['dono'])->post(route('clientes.store'), [
            'nome' => '  carlos   EDUARDO  ',
            'telefone' => '11987654321',
        ])->assertSessionHasErrors('telefone');

        // 4. Valida edição (update): bloqueia duplicidade com outro cliente, permite editar o próprio
        $this->actingAs($alfa['dono'])->post(route('clientes.store'), [
            'nome' => 'Marina Souza',
            'email' => 'marina@cliente.test',
            'telefone' => '(11) 97777-8888',
        ])->assertRedirect();

        $marina = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $alfa['empresa']->id)
            ->where('email', 'marina@cliente.test')
            ->firstOrFail();

        $this->actingAs($alfa['dono'])->put(route('clientes.update', $marina), [
            'nome' => 'Marina Souza',
            'email' => 'carlos@cliente.test',
            'telefone' => '(11) 97777-8888',
        ])->assertSessionHasErrors('email');

        $this->actingAs($alfa['dono'])->put(route('clientes.update', $marina), [
            'nome' => 'Carlos Eduardo',
            'email' => 'marina@cliente.test',
            'telefone' => '11987654321',
        ])->assertSessionHasErrors('telefone');

        $this->actingAs($alfa['dono'])->put(route('clientes.update', $carlos), [
            'nome' => 'Carlos Eduardo',
            'email' => 'carlos@cliente.test',
            'telefone' => '(11) 98765-4321',
            'cidade' => 'São Paulo',
        ])->assertRedirect(route('clientes.show', $carlos));

        // 5. Outra empresa pode cadastrar cliente com mesmo nome + telefone sem colisão
        $this->actingAs($beta['dono'])->post(route('clientes.store'), [
            'nome' => 'Carlos Eduardo',
            'email' => 'carlos@cliente.test',
            'telefone' => '(11) 98765-4321',
        ])->assertRedirect();

        // 6. Fluxo de vinculação de conta de usuário existente sem empresa continua funcionando normalmente
        $usuarioLivre = User::create([
            'name' => 'Patrícia Conta Livre',
            'email' => 'patricia.livre@cliente.test',
            'password' => 'SenhaForte123',
            'telefone' => '(11) 95555-6666',
            'tipo_usuario' => 'cliente',
            'ativo' => true,
        ]);

        $this->actingAs($alfa['dono'])->post(route('clientes.store'), [
            'nome' => 'Patrícia Conta Livre',
            'email' => 'patricia.livre@cliente.test',
            'telefone' => '(11) 95555-6666',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'empresa_id' => $alfa['empresa']->id,
            'user_id' => $usuarioLivre->id,
        ]);
    }

    public function test_bloqueia_nome_fantasia_duplicado_em_empresas_e_permite_filiais_e_reuso_apos_exclusao(): void
    {
        $admin = User::create([
            'name' => 'Admin Empresas',
            'email' => 'admin.dup.emp@krdetail.test',
            'password' => 'SenhaForte123',
            'tipo_usuario' => 'administrador',
            'ativo' => true,
        ]);

        // 1. Cria empresa matriz
        $this->actingAs($admin)->post(route('configuracoes.empresas.store'), [
            'nome_fantasia' => 'Garage Prime Matriz',
            'razao_social' => 'Garage Prime Estética Automotiva LTDA',
            'whatsapp' => '(11) 99999-1111',
            'email' => 'matriz@garageprime.test',
        ])->assertRedirect();

        $matriz = Empresa::where('nome_fantasia', 'Garage Prime Matriz')->firstOrFail();

        // 2. Bloqueia nome_fantasia duplicado (mesmo com variação de maiúsculas e espaços)
        $this->actingAs($admin)->post(route('configuracoes.empresas.store'), [
            'nome_fantasia' => '  garage   PRIME   matriz ',
            'razao_social' => 'Outra Razão LTDA',
            'whatsapp' => '(11) 98888-2222',
            'email' => 'dup@garageprime.test',
        ])->assertSessionHasErrors('nome_fantasia');

        // 3. Permite filial com nome_fantasia distinto mesmo mantendo a mesma razao_social
        $this->actingAs($admin)->post(route('configuracoes.empresas.store'), [
            'nome_fantasia' => 'Garage Prime Filial Sul',
            'razao_social' => 'Garage Prime Estética Automotiva LTDA',
            'whatsapp' => '(11) 97777-3333',
            'email' => 'filial@garageprime.test',
        ])->assertRedirect();

        $filial = Empresa::where('nome_fantasia', 'Garage Prime Filial Sul')->firstOrFail();

        // 4. Update permite manter próprio nome_fantasia e bloqueia adotar nome_fantasia de outra ativa
        $this->actingAs($admin)->put(route('configuracoes.empresas.update', $filial), [
            'nome_fantasia' => 'Garage Prime Filial Sul',
            'razao_social' => 'Garage Prime Estética Automotiva LTDA',
            'whatsapp' => '(11) 97777-3334',
            'email' => 'filial@garageprime.test',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($admin)->put(route('configuracoes.empresas.update', $filial), [
            'nome_fantasia' => 'garage prime matriz',
            'razao_social' => 'Garage Prime Estética Automotiva LTDA',
            'whatsapp' => '(11) 97777-3334',
            'email' => 'filial@garageprime.test',
        ])->assertSessionHasErrors('nome_fantasia');

        // 5. Após exclusão (soft delete), o nome_fantasia pode ser reutilizado
        $this->actingAs($admin)->delete(route('configuracoes.empresas.destroy', $filial))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSoftDeleted('empresas', ['id' => $filial->id]);

        $this->actingAs($admin)->post(route('configuracoes.empresas.store'), [
            'nome_fantasia' => 'Garage Prime Filial Sul',
            'razao_social' => 'Garage Prime Estética Automotiva LTDA',
            'whatsapp' => '(11) 96666-4444',
            'email' => 'filial.nova@garageprime.test',
        ])->assertRedirect()->assertSessionHas('success');
    }
}
