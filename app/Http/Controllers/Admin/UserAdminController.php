<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\KycProfile;
use App\Models\Cagnotte;
use App\Models\Withdrawal;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAdminController extends Controller
{
    public function categories()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                ['key' => 'Sante', 'label' => 'Santé'],
                ['key' => 'Education', 'label' => 'Education'],
                ['key' => 'Urgence', 'label' => 'Urgence'],
                ['key' => 'Obseques', 'label' => 'Obsèques'],
                ['key' => 'Autres', 'label' => 'Autres'],
            ],
        ]);
    }

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
                ->withCount('cagnottes')
                ->withSum('cagnottes', 'montant_recolte')
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

    public function overview()
    {
        try {
            $totalCollected = (string) Cagnotte::where('est_publiee', true)->sum('montant_recolte');

            $activeCagnottes = Cagnotte::where('est_publiee', true)
                ->where(function ($q) {
                    $q->whereNull('date_limite')->orWhere('date_limite', '>', now());
                })
                ->where(function ($q) {
                    $q->whereColumn('montant_recolte', '<', 'objectif')
                      ->orWhereNull('objectif');
                })
                ->count();

            $newUsersThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

            $totalKyc = KycProfile::count();
            $approvedKyc = KycProfile::where('status', 'approved')->count();
            $kycRate = $totalKyc > 0 ? round(($approvedKyc / $totalKyc) * 100, 1) : 0.0;

            $categoryRows = Cagnotte::select('categorie', DB::raw('COUNT(*) as count'))
                ->whereNotNull('categorie')
                ->groupBy('categorie')
                ->get();
            $totalCats = (int) $categoryRows->sum('count');
            $categories = $categoryRows->map(function ($row) use ($totalCats) {
                $pct = $totalCats > 0 ? round(($row->count * 100) / $totalCats, 1) : 0.0;
                return [
                    'name' => $row->categorie,
                    'count' => (int) $row->count,
                    'percent' => $pct,
                ];
            })->values();

            $kycPending = KycProfile::where('status', 'pending')->count();
            $cagnottesInReview = Cagnotte::where('est_previsualisee', true)->where('est_publiee', false)->count();
            $withdrawalsPending = Withdrawal::where('status', 'pending')->count();
            $urgentReports = Report::where('status', 'pending')->where('is_urgent', true)->count();

            $recentCagnottes = Cagnotte::with('user')->latest()->limit(5)->get()
                ->map(function ($c) {
                    return [
                        'type' => 'cagnotte_created',
                        'id' => $c->id,
                        'titre' => $c->titre,
                        'user' => optional($c->user)->name,
                        'created_at' => $c->created_at?->toISOString(),
                    ];
                });

            $recentKyc = KycProfile::with('user')->orderByDesc('updated_at')->limit(5)->get()
                ->map(function ($k) {
                    return [
                        'type' => 'kyc_update',
                        'id' => $k->id,
                        'status' => $k->status,
                        'user' => optional($k->user)->name,
                        'updated_at' => $k->updated_at?->toISOString(),
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'kpis' => [
                        'total_collected' => $totalCollected,
                        'active_cagnottes' => $activeCagnottes,
                        'new_users_this_month' => $newUsersThisMonth,
                        'kyc_validated_percent' => $kycRate,
                    ],
                    'categories' => $categories,
                    'actions' => [
                        'kyc_to_validate' => $kycPending,
                        'withdrawals_to_validate' => $withdrawalsPending,
                        'urgent_reports' => $urgentReports,
                        'cagnottes_in_review' => $cagnottesInReview,
                    ],
                    'recent' => [
                        'cagnottes' => $recentCagnottes,
                        'kyc' => $recentKyc,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du chargement du tableau de bord.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function counters(Request $request)
    {
        try {
            $request->validate([
                'q' => 'nullable|string',
                'role' => 'nullable|in:admin,user',
                'is_active' => 'nullable|boolean',
            ]);

            $base = User::query()
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

            $total = (clone $base)->count();
            $active = (clone $base)->where('is_active', true)->count();
            $admins = (clone $base)->where('role', 'admin')->count();

            return response()->json([
                'status' => 'success',
                'data' => compact('total', 'active', 'admins'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de calculer les compteurs.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
