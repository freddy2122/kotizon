<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportAdminController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:pending,resolved',
                'urgent' => 'nullable|boolean',
                'user_id' => 'nullable|integer',
                'cagnotte_id' => 'nullable|integer',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $q = Report::query()
                ->with(['user','cagnotte'])
                ->when($request->filled('status'), fn($qq) => $qq->where('status', $request->string('status')))
                ->when($request->filled('urgent'), fn($qq) => $qq->where('is_urgent', (bool) $request->boolean('urgent')))
                ->when($request->filled('user_id'), fn($qq) => $qq->where('user_id', (int) $request->integer('user_id')))
                ->when($request->filled('cagnotte_id'), fn($qq) => $qq->where('cagnotte_id', (int) $request->integer('cagnotte_id')));

            $perPage = (int) $request->input('per_page', 20);
            $rows = $q->orderByDesc('id')->paginate($perPage);
            return response()->json(['status' => 'success', 'data' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Impossible de lister les signalements.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function show($id)
    {
        try {
            $row = Report::with(['user','cagnotte'])->findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Signalement introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors de la récupération du signalement.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function resolve($id)
    {
        try {
            $row = Report::findOrFail($id);
            if ($row->status === 'resolved') {
                return response()->json(['status' => 'success', 'message' => 'Déjà résolu.', 'data' => $row]);
            }
            $row->status = 'resolved';
            $row->resolved_at = now();
            $row->save();
            return response()->json(['status' => 'success', 'message' => 'Signalement résolu.', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Signalement introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors de la résolution du signalement.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }
}
