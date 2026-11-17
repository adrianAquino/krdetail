<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function criarUsuarioAtivo(): User
    {
        $empresa = Empresa::first() ?? Empresa::create([
            'nome_fantasia' => 'Kraus Detail Reset',
            'razao_social' => 'Kraus Detail Reset LTDA',
            'whatsapp' => '(11) 99999-8888',
            'email' => 'reset@krausdetail.test',
            'ativo' => true,
        ]);

        return User::create([
            'empresa_id' => $empresa->id,
            'name' => 'Usuário Teste Senha',
            'email' => 'usuario.reset@krdetail.test',
            'password' => 'SenhaAntiga123',
            'telefone' => '(11) 98888-7777',
            'tipo_usuario' => 'cliente',
            'ativo' => true,
        ]);
    }

    public function test_renderiza_telas_de_recuperacao_e_redefinicao_de_senha(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Esqueci minha senha');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Esqueceu sua senha?');

        $this->get(route('password.reset', ['token' => 'token-exemplo', 'email' => 'teste@krdetail.test']))
            ->assertOk()
            ->assertSee('Criar Nova Senha')
            ->assertSee('teste@krdetail.test');
    }

    public function test_solicitacao_para_email_existente_gera_token_e_envia_notificacao_em_portugues(): void
    {
        Notification::fake();

        $user = $this->criarUsuarioAtivo();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user) {
                $mail = $notification->toMail($user);

                $this->assertSame('Recuperação de Senha - Kraus Detail', $mail->subject);
                $this->assertSame('Redefinir Minha Senha', $mail->actionText);
                $this->assertStringContainsString($notification->token, $mail->actionUrl);

                return true;
            }
        );
    }

    public function test_solicitacao_para_email_inexistente_retorna_erro_e_nao_envia_notificacao(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'naoexiste@krdetail.test',
        ]);

        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'naoexiste@krdetail.test',
        ]);
    }

    public function test_redefinicao_com_token_valido_altera_senha_permite_login_e_invalida_token(): void
    {
        Notification::fake();

        $user = $this->criarUsuarioAtivo();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionHas('success');

        $tokenCapturado = null;
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$tokenCapturado) {
                $tokenCapturado = $notification->token;
                return true;
            }
        );

        $this->assertNotNull($tokenCapturado);

        $response = $this->post(route('password.update'), [
            'token' => $tokenCapturado,
            'email' => $user->email,
            'password' => 'NovaSenhaForte99',
            'password_confirmation' => 'NovaSenhaForte99',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NovaSenhaForte99', $user->password));
        $this->assertFalse(Hash::check('SenhaAntiga123', $user->password));

        // Token deve ter sido removido após o uso
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // Login funciona com a nova senha
        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'NovaSenhaForte99',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_redefinicao_com_token_invalido_e_bloqueada(): void
    {
        $user = $this->criarUsuarioAtivo();

        $response = $this->post(route('password.update'), [
            'token' => 'token-totalmente-invalido',
            'email' => $user->email,
            'password' => 'NovaSenhaForte99',
            'password_confirmation' => 'NovaSenhaForte99',
        ]);

        $response->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue(Hash::check('SenhaAntiga123', $user->password));
        $this->assertFalse(Hash::check('NovaSenhaForte99', $user->password));
    }
}
