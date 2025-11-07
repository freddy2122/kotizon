<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Withdrawal;
use App\Models\Cagnotte;

class WithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $q = Withdrawal::query()
            ->with('cagnotte')
            ->where('user_id', $user->id)
            ->when($request->filled('status'), fn($qq) => $qq->where('status', $request->string('status')));

        $perPage = (int) $request->input('per_page', 20);
        $rows = $q->orderByDesc('id')->paginate($perPage);
        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        $data = $request->validate([
            'cagnotte_id' => 'nullable|integer|exists:cagnottes,id',
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        // Optional rule: if cagnotte_id provided, ensure it belongs to user
        if (!empty($data['cagnotte_id'])) {
            $own = Cagnotte::where('id', $data['cagnotte_id'])->where('user_id', $user->id)->exists();
            if (!$own) {
                return response()->json(['status' => 'forbidden', 'message' => 'Cette cagnotte ne vous appartient pas.'], 403);
            }
        }

        $row = Withdrawal::create([
            'user_id' => $user->id,
            'cagnotte_id' => $data['cagnotte_id'] ?? null,
            'amount' => $data['amount'],
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Demande de retrait créée.', 'data' => $row], 201);
    }
}
