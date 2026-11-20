<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
    }

    /**
     * Canais de entrega da notificação.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Constrói a mensagem de e-mail personalizada em Português para redefinição de senha.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $relativeUrl = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        $appUrl = rtrim((string) config('app.url'), '/');
        $url = $appUrl !== ''
            ? $appUrl . $relativeUrl
            : route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], true);

        $empresaNome = $notifiable->empresa?->nome_fantasia ?: 'Kraus Detail';
        $nomeUsuario = trim((string) ($notifiable->name ?? '')) ?: 'usuário';
        $broker = (string) config('auth.defaults.passwords', 'users');
        $expireMinutes = (int) config("auth.passwords.{$broker}.expire", 60);

        return (new MailMessage)
            ->subject("Recuperação de Senha - {$empresaNome}")
            ->greeting("Olá, {$nomeUsuario}!")
            ->line("Você está recebendo este e-mail porque solicitou a redefinição de senha da sua conta na {$empresaNome}.")
            ->action('Redefinir Minha Senha', $url)
            ->line("Este link de redefinição de senha expira em {$expireMinutes} minutos.")
            ->line('Se você não solicitou a alteração da sua senha, nenhuma ação é necessária e sua senha atual permanecerá ativa.');
    }
}
