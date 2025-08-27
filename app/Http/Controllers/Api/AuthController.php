<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Twilio\Rest\Client;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users',
            'phone'    => [
                'required',
                'string',
                'unique:users',
                'regex:/^\+[1-9]\d{7,14}$/'
            ],
            'password' => 'required|string|min:6',
        ], [
            'phone.regex' => 'Le numéro doit être au format international, ex : +22961234567.'
        ]);


        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Générer code unique
        $verificationCode = 'KOT-' . strtoupper(Str::random(6));

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'password'          => Hash::make($request->password),
            'verification_code' => $verificationCode,
            'is_verified'       => false,
            'is_phone_verified' => false,
        ]);

        // 1. Envoi EMAIL
        Mail::to($user->email)->send(new VerificationCodeMail($verificationCode));

        // 2. Envoi WhatsApp via Twilio
        $sid    = env('TWILIO_SID');
        $token  = env('TWILIO_AUTH_TOKEN');
        $twilio = new Client($sid, $token);

        $from = "whatsapp:+14155238886"; // Numéro Twilio Sandbox
        $to   = "whatsapp:" . $user->phone; // Exemple : whatsapp:+229XXXXXXXX

        $message = "Bonjour {$user->name}, votre code de vérification est : {$verificationCode}";

        $twilio->messages->create($to, [
            "from" => $from,
            "body" => $message
        ]);

        return response()->json([
            'message' => 'Inscription réussie. Code envoyé par email et WhatsApp.',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code'  => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)
            ->where('verification_code', $request->code)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Code invalide.'], 400);
        }

        $user->update([
            'is_verified'       => true,
            'is_phone_verified' => true,
            'email_verified_at' => now(),
            'verification_code' => null,
        ]);

        return response()->json(['message' => 'Compte vérifié avec succès.']);
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name'         => $googleUser->getName(),
                    'password'     => null,
                    'is_verified'  => true, // email validé
                    'email_verified_at' => now(),
                    // phone à remplir plus tard
                    'is_phone_verified' => false,
                ]
            );

            // Après ça, tu dois demander à l’utilisateur de saisir son numéro


            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de l\'authentification Google.'], 500);
        }
    }



    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        if (!$user->is_verified) {
            return response()->json(['message' => 'Compte non vérifié.'], 403);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Compte suspendu.'], 403);
        }

        if ($user->kycProfile && $user->kycProfile->status !== 'approved') {
            return response()->json(['message' => 'Votre compte n’est pas encore vérifié (KYC en attente ou rejeté).'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }



    public function googleLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->id_token);

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'password' => null,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'is_phone_verified' => true,

                ]
            );

            if ($user->kycProfile && $user->kycProfile->status !== 'approved') {
                return response()->json(['message' => 'Votre compte n’est pas encore vérifié (KYC en attente ou rejeté).'], 403);
            }

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur Google login', 'error' => $e->getMessage()], 500);
        }
    }


    public function me(Request $request)
    {
        return response()->json($request->user());
    }


    public function sendResetLinkEmail(Request $request)
    {
        // Validation de l'email
        $request->validate([
            'email' => 'required|email',
        ]);

        // Envoi du lien de réinitialisation
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Gestion du throttling
        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Veuillez patienter quelques instants avant de demander un nouveau lien.'
            ], 429);
        }

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => __($status)
            ], 200);
        }

        return response()->json([
            'message' => __($status)
        ], 400);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['status' => __($status)], 200)
            : response()->json(['message' => __($status)], 400);
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }
}
