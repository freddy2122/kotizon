<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CagnotteRequest;
use Illuminate\Http\Request;

class CagnotteRequestAdminController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:pending,in_review,approved,rejected',
                'user_id' => 'nullable|integer',
                'q' => 'nullable|string',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $q = CagnotteRequest::query()
                ->with('user')
                ->when($request->filled('status'), fn($qq) => $qq->where('status', $request->string('status')))
                ->when($request->filled('user_id'), fn($qq) => $qq->where('user_id', (int)$request->integer('user_id')))
                ->when($request->filled('q'), function ($qq) use ($request) {
                    $term = '%' . str_replace('%', '\\%', $request->string('q')) . '%';
                    $qq->where(function ($sub) use ($term) {
                        $sub->where('title', 'like', $term)
                           ->orWhere('message', 'like', $term);
                    });
                });

            $perPage = (int) $request->input('per_page', 20);
            $rows = $q->orderByDesc('id')->paginate($perPage);
            return response()->json(['status' => 'success', 'data' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Impossible de lister les demandes.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function show($id)
    {
        try {
            $row = CagnotteRequest::with('user')->findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Demande introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors de la récupération de la demande.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function approve($id)
    {
        try {
            $row = CagnotteRequest::findOrFail($id);
            $row->status = 'approved';
            $row->approved_at = now();
            $row->reviewed_at = now();
            $row->save();
            return response()->json(['status' => 'success', 'message' => 'Demande approuvée.', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Demande introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors de l\'approbation de la demande.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    public function reject($id, Request $request)
    {
        try {
            $data = $request->validate(['reason' => 'nullable|string|max:255']);
            $row = CagnotteRequest::findOrFail($id);
            $row->status = 'rejected';
            $row->reviewed_at = now();
            $row->rejected_at = now();
            // Optionnel: stocker le motif dans message ou autre colonne dédiée
            if (!empty($data['reason'])) {
                $row->message = trim(($row->message ? ($row->message."\n\n") : '') . 'Admin rejection reason: ' . $data['reason']);
            }
            $row->save();
            return response()->json(['status' => 'success', 'message' => 'Demande rejetée.', 'data' => $row]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'not_found', 'message' => 'Demande introuvable.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erreur lors du rejet de la demande.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }
}
