<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\SendWhatsappVerification;

class DebugController extends Controller
{
    public function whatsapp(Request $request)
    {
        $data = $request->validate([
            'to' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        $to = $data['to'] ?? $request->user()->phone ?? null;
        if (!$to) {
            return response()->json([
                'message' => "Numéro 'to' manquant et aucun numéro associé à l'utilisateur."
            ], 422);
        }

        $message = $data['message'] ?? 'Test WhatsApp depuis Kotizon API';

        try {
            SendWhatsappVerification::dispatch($to, $message);
            return response()->json([
                'message' => 'Job WhatsApp dispatché',
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Echec du dispatch WhatsApp',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
