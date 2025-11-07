<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class WithdrawalAdminController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:pending,approved,rejected',
                'user_id' => 'nullable|integer',
                'cagnotte_id' => 'nullable|integer',
                'from' => 'nullable|date',
                'to' => 'nullable|date',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $q = Withdrawal::query()
                ->with(['user','cagnotte'])
                ->when($request->filled('status'), fn($qq) => $qq->where('status', $request->string('status')))
                ->when($request->filled('user_id'), fn($qq) => $qq->where('user_id', (int) $request->integer('user_id')))
                ->when($request->filled('cagnotte_id'), fn($qq) => $qq->where('cagnotte_id', (int) $request->integer('cagnotte_id')))
                ->when($request->filled('from'), fn($qq) => $qq->where('created_at', '>=', $request->date('from')))
                ->when($request->filled('to'), fn($qq) => $qq->where('created_at', '<=', $request->date('to')));

            $perPage = (int) $request->input('per_page', 20);
            $rows = $q->orderByDesc('id')->paginate($perPage);

            return response()->json(['status' => 'success', 'data' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Impossible de lister les retraits.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function show($id)
    {
        try {
            $row = Withdrawal::with(['user','cagnotte'])->findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Retrait introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors de la récupération du retrait.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function approve($id, Request $request)
    {
        try {
            $row = Withdrawal::findOrFail($id);
            if ($row->status !== 'pending') {
                return response()->json(['status' => 'validation_error', 'message' => 'Ce retrait n\'est pas en attente.'], 422);
            }
            $row->status = 'approved';
            $row->processed_at = now();
            $row->save();
            return response()->json(['status' => 'success', 'message' => 'Retrait approuvé.', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Retrait introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors de l\'approbation du retrait.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function reject($id, Request $request)
    {
        try {
            $data = $request->validate(['reason' => 'nullable|string|max:255']);
            $row = Withdrawal::findOrFail($id);
            if ($row->status !== 'pending') {
                return response()->json(['status' => 'validation_error', 'message' => 'Ce retrait n\'est pas en attente.'], 422);
            }
            $row->status = 'rejected';
            $row->reason = $data['reason'] ?? null;
            $row->processed_at = now();
            $row->save();
            return response()->json(['status' => 'success', 'message' => 'Retrait rejeté.', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Retrait introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors du rejet du retrait.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }
}
