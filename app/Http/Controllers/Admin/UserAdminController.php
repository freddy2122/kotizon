<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'q' => 'nullable|string',
                'role' => 'nullable|in:admin,user',
                'is_active' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = User::query()
                ->when($request->filled('role'), fn($q) => $q->where('role', $request->role))
                ->when($request->filled('is_active'), fn($q) => $q->where('is_active', (bool)$request->boolean('is_active')))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . $request->q . '%';
                    $q->where(function ($sub) use ($term) {
                        $sub->where('name', 'like', $term)
                           ->orWhere('email', 'like', $term)
                           ->orWhere('phone', 'like', $term)
                           ->orWhere('country', 'like', $term)
                           ->orWhere('city', 'like', $term);
                    });
                });

            $perPage = (int) $request->input('per_page', 20);
            $users = $query->orderByDesc('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $users,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Impossible de lister les utilisateurs.",
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = User::with('kycProfile')->findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $user,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Utilisateur introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Erreur lors de la récupération de l'utilisateur.",
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $user = User::findOrFail($id);

            // Prevent self-deactivation for current admin
            if (auth()->id() === $user->id && $user->role === 'admin' && $user->is_active) {
                return response()->json([
                    'status' => 'forbidden',
                    'message' => "Vous ne pouvez pas désactiver votre propre compte administrateur."
                ], 403);
            }

            $user->is_active = !$user->is_active;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => $user->is_active ? 'Utilisateur activé.' : 'Utilisateur désactivé.',
                'data' => $user,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Utilisateur introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Erreur lors du changement de statut de l'utilisateur.",
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function setRole($id, Request $request)
    {
        try {
            $request->validate([
                'role' => 'required|in:admin,user',
            ]);

            $user = User::findOrFail($id);

            // Prevent removing last admin
            if ($user->role === 'admin' && $request->role === 'user') {
                $adminCount = User::where('role', 'admin')->where('is_active', true)->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'status' => 'forbidden',
                        'message' => "Impossible de retirer le dernier administrateur actif."
                    ], 403);
                }
            }

            $user->role = $request->role;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Rôle mis à jour.',
                'data' => $user,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Utilisateur introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Erreur lors de la mise à jour du rôle.",
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
