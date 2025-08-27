<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\User;

class KycSubmittedNotification extends Notification
{
    use Queueable;

    protected $user; // l’utilisateur qui a soumis son KYC

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    // Canaux de notification
    public function via($notifiable)
    {
        return ['mail', 'database']; // mail + base de données
    }

    // Mail
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Nouvelle demande KYC soumise')
                    ->line('Un utilisateur a soumis son profil KYC pour validation.')
                    ->line('Nom : ' . $this->user->name)
                    ->line('Email : ' . $this->user->email)
                    ->action('Voir les demandes KYC', url('/admin/kyc-profiles'))
                    ->line('Merci de vérifier rapidement cette demande.');
    }

    // Stocker dans la base (notifications table)
    public function toDatabase($notifiable)
    {
        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'message' => 'Nouvelle demande KYC soumise.',
        ];
    }
}
