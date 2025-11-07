<?php

namespace App\Http\Controllers;

use App\Models\CagnotteRequest;
use Illuminate\Http\Request;

class CagnotteRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'unauthenticated', 'message' => 'Utilisateur non authentifié.'], 401);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'categorie' => 'nullable|string|max:100',
            'objectif_propose' => 'nullable|numeric|min:0',
            'message' => 'nullable|string',
        ]);

        $row = CagnotteRequest::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'categorie' => $data['categorie'] ?? null,
            'objectif_propose' => $data['objectif_propose'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Demande de création soumise.',
            'data' => $row,
        ], 201);
    }
}
