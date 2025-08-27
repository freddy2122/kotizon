<?php

namespace App\Http\Controllers;

use App\Models\Cagnotte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CagnotteController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        // Conditions : email vérifié + KYC approuvé
        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Votre email n\'est pas vérifié.'], 403);
        }

        if (!$user->kycProfile || $user->kycProfile->status !== 'approved') {
            return response()->json(['message' => 'Votre profil KYC n\'est pas encore approuvé.'], 403);
        }

        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'categorie' => 'required|string|max:255',
            'objectif' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'nullable|url',
            'modele_texte' => 'nullable|string',
        ]);

        $validated['user_id'] = $user->id;

        $cagnotte = Cagnotte::create($validated);

        return response()->json([
            'message' => 'Cagnotte créée avec succès.',
            'cagnotte' => $cagnotte,
        ]);
    }
}
