<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Twilio\Rest\Client;

class SendWhatsappVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $message;

    public function __construct(string $to, string $message)
    {
        $this->to = $to;
        $this->message = $message;
    }

    public function handle(): void
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from', 'whatsapp:+14155238886');

        if (!$sid || !$token) {
            // Pas de credentials en local: on sort sans erreur
            return;
        }

        $twilio = new Client($sid, $token);
        $twilio->messages->create('whatsapp:' . $this->to, [
            'from' => $from,
            'body' => $this->message,
        ]);
    }
}
