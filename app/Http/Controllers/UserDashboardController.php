<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cagnotte;
use App\Models\Donation;
use App\Models\Withdrawal;

class UserDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        // Solde disponible = dons réussis - retraits approuvés sur les cagnottes de l'utilisateur
        $cagnotteIds = Cagnotte::where('user_id', $user->id)->pluck('id');

        $donationsTotal = Donation::whereIn('cagnotte_id', $cagnotteIds)
            ->where('status', 'succeeded')
            ->sum('amount');

        $withdrawalsTotal = Withdrawal::whereIn('cagnotte_id', $cagnotteIds)
            ->where('status', 'approved')
            ->sum('amount');

        $balance = (string) ($donationsTotal - $withdrawalsTotal);

        $totalCollected = (string) Cagnotte::where('user_id', $user->id)->sum('montant_recolte');
        $activeCagnottes = Cagnotte::where('user_id', $user->id)->where('est_publiee', true)->count();
        $totalCagnottes = Cagnotte::where('user_id', $user->id)->count();

        $recentDonations = Donation::with(['cagnotte','user'])
            ->whereIn('cagnotte_id', $cagnotteIds)
            ->where('status', 'succeeded')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($d) {
                return [
                    'id' => $d->id,
                    'amount' => (string) $d->amount,
                    'currency' => $d->currency,
                    'cagnotte' => [
                        'id' => $d->cagnotte?->id,
                        'titre' => $d->cagnotte?->titre,
                    ],
                    'donor' => $d->user ? [
                        'id' => $d->user->id,
                        'name' => $d->user->name,
                    ] : null,
                    'created_at' => $d->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'kpis' => [
                    'balance' => $balance,
                    'total_collected' => $totalCollected,
                    'active_cagnottes' => $activeCagnottes,
                    'total_cagnottes' => $totalCagnottes,
                ],
                'recent_donations' => $recentDonations,
            ],
        ]);
    }

    public function donations(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $perPage = (int) $request->input('per_page', 20);

        $cagnotteIds = Cagnotte::where('user_id', $user->id)->pluck('id');

        $rows = Donation::with(['cagnotte','user'])
            ->whereIn('cagnotte_id', $cagnotteIds)
            ->where('status', 'succeeded')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }
}
