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
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], true);

        return (new MailMessage)
            ->subject('Recuperação de Senha - Kraus Detail')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Você está recebendo este e-mail porque solicitou a redefinição de senha da sua conta na Kraus Detail.')
            ->action('Redefinir Minha Senha', $url)
            ->line('Este link de redefinição de senha expira em 60 minutos.')
            ->line('Se você não solicitou a alteração da sua senha, nenhuma ação é necessária e sua senha atual permanecerá ativa.');
    }
}
