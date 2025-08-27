<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class KycStatusChangedNotification extends Notification
{
    use Queueable;

    protected $status;

    public function __construct($status)
    {
        $this->status = $status; // 'approved' ou 'rejected'
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $message = $this->status === 'approved' 
            ? 'Votre profil KYC a été validé avec succès.'
            : 'Votre profil KYC a été rejeté. Veuillez vérifier et soumettre à nouveau.';

        return (new MailMessage)
                    ->subject('Mise à jour de votre KYC')
                    ->line($message)
                    ->action('Se connecter', url('/login'))
                    ->line('Merci pour votre confiance.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => $this->status === 'approved' 
                ? 'Votre KYC a été validé.'
                : 'Votre KYC a été rejeté.',
        ];
    }
}
