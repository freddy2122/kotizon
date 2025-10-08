<?php

namespace App\Http\Controllers;

use App\Models\KycProfile;
use App\Models\User;
use App\Models\KycDocument;
use App\Notifications\KycStatusChangedNotification;
use App\Notifications\KycSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class KycController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'national_id'    => 'required|string',
            'document_type'  => 'required|in:CNI,PASSEPORT,PERMIS',
            'document_front' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'document_back'  => 'required|file|mimes:jpg,jpeg,png,pdf',
            'date_of_birth'  => 'required|date',
            'country'        => 'required|string',
            'address'        => 'required|string',
            // Si tu as ajouté selfie, ajoute-le ici :
            'selfie'         => 'nullable|file|mimes:jpg,jpeg,png',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if ($user->role === 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'Les administrateurs ne peuvent pas soumettre un KYC.'
            ], 403);
        }
        /** @var User $user */

        $front = Storage::disk('public')->putFile('kyc_documents', $request->file('document_front'));
        $back = Storage::disk('public')->putFile('kyc_documents', $request->file('document_back'));
        $selfie = $request->hasFile('selfie')
            ? Storage::disk('public')->putFile('kyc_documents', $request->file('selfie'))
            : null;

        $profile = $user->kycProfile()->create([
            'national_id'    => $request->national_id,
            'document_type'  => $request->document_type,
            'document_front' => 'storage/' . $front,
            'document_back'  => 'storage/' . $back,
            'date_of_birth'  => $request->date_of_birth,
            'country'        => $request->country,
            'address'        => $request->address,
            'selfie'         => $selfie ? 'storage/' . $selfie : null,
            'status'         => 'pending',
        ]);

        $admins = User::where('role', 'admin')->get();

        Notification::send($admins, new KycSubmittedNotification($user));

        return response()->json(['message' => 'KYC soumis avec succès, en attente de validation.']);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $profile = $user->kycProfile;
        if (!$profile) {
            return response()->json([
                'status' => 'none',
                'submitted' => false,
                'requirements' => $this->buildRequirements($request->query('country')),
                'documents' => [],
            ]);
        }
        $docs = $profile->documents()->get()->map(function ($d) {
            return [
                'id' => (string)$d->id,
                'type' => $d->type,
                'url' => $d->path,
            ];
        });

        return response()->json([
            'status' => $profile->status,
            'submitted' => (bool)($profile->submitted ?? false),
            'requirements' => $this->buildRequirements($request->query('country', $profile->country ?? null)),
            'documents' => $docs,
            'profile' => $profile,
        ]);
    }



    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:pending,approved,rejected',
                'q' => 'nullable|string',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            $query = KycProfile::with('user')
                ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . $request->q . '%';
                    $q->where(function ($sub) use ($term) {
                        $sub->where('national_id', 'like', $term)
                            ->orWhere('country', 'like', $term)
                            ->orWhere('address', 'like', $term)
                            ->orWhereHas('user', function ($uq) use ($term) {
                                $uq->where('name', 'like', $term)
                                   ->orWhere('email', 'like', $term)
                                   ->orWhere('phone', 'like', $term);
                            });
                    });
                });

            $perPage = (int)($request->input('per_page', 20));
            $result = $query->orderByDesc('id')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de lister les profils KYC.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $profile = KycProfile::with('user')->findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $profile,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Profil KYC introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du profil KYC.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function validateKyc($id)
    {
        try {
            $profile = KycProfile::findOrFail($id);
            $profile->update(['status' => 'approved', 'rejection_reason' => null]);

            // Notifier l'utilisateur que KYC validé
            $profile->user->notify(new KycStatusChangedNotification('approved'));

            return response()->json([
                'status' => 'success',
                'message' => 'KYC validé avec succès.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Profil KYC introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la validation du KYC.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function rejectKyc($id, Request $request)
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:1000'
            ]);

            $profile = KycProfile::findOrFail($id);
            $profile->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('reason')
            ]);

            // Notifier l'utilisateur que KYC rejeté
            $profile->user->notify(new KycStatusChangedNotification('rejected'));

            return response()->json([
                'status' => 'success',
                'message' => 'KYC rejeté avec succès.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Profil KYC introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du rejet du KYC.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $profile = KycProfile::findOrFail($id);
            $profile->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'KYC supprimé avec succès.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Profil KYC introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression du KYC.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // =========================
    // Frontend Workflow Endpoints
    // =========================

    public function requirements(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return response()->json([
            'status' => 'success',
            'requirements' => $this->buildRequirements($request->query('country')),
        ]);
    }

    public function uploadDocument(Request $request)
    {
        $user = $request->user();
        if (!$user) { return response()->json(['message' => 'Unauthenticated'], 401); }
        if ($user->role === 'admin') {
            return response()->json(['status' => 'forbidden', 'message' => 'Les administrateurs ne peuvent pas soumettre un KYC.'], 403);
        }

        $request->validate([
            'type' => 'required|string',
            'side' => 'nullable|string|in:front,back',
            'file' => 'required|file|max:5120|mimes:jpeg,jpg,png,pdf',
        ]);

        $profile = $user->kycProfile ?: $user->kycProfile()->create([
            'status' => 'pending',
            'submitted' => false,
            'country' => $request->input('country'),
            'address' => $request->input('address'),
        ]);

        $stored = Storage::disk('public')->putFile('kyc_documents', $request->file('file'));
        if (!$stored) {
            return response()->json(['status' => 'storage_error', 'message' => "Échec de l'upload du document."], 500);
        }

        $doc = $profile->documents()->create([
            'type' => $request->input('type'),
            'side' => $request->input('side'),
            'path' => 'storage/' . $stored,
            'mime' => $request->file('file')->getClientMimeType(),
            'size' => $request->file('file')->getSize(),
            'status' => 'uploaded',
        ]);

        return response()->json([
            'id' => (string)$doc->id,
            'type' => $doc->type,
            'url' => $doc->path,
        ], 201);
    }

    public function replaceDocument($id, Request $request)
    {
        $user = $request->user();
        if (!$user) { return response()->json(['message' => 'Unauthenticated'], 401); }
        if ($user->role === 'admin') {
            return response()->json(['status' => 'forbidden', 'message' => 'Les administrateurs ne peuvent pas soumettre un KYC.'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:5120|mimes:jpeg,jpg,png,pdf',
        ]);

        $profile = $user->kycProfile;
        if (!$profile) { return response()->json(['status' => 'not_found', 'message' => 'Profil KYC introuvable.'], 404); }

        $doc = $profile->documents()->where('id', $id)->first();
        if (!$doc) { return response()->json(['status' => 'not_found', 'message' => 'Document introuvable.'], 404); }

        if (!empty($doc->path) && str_starts_with($doc->path, 'storage/')) {
            $diskPath = substr($doc->path, strlen('storage/'));
            try { Storage::disk('public')->delete($diskPath); } catch (\Throwable $e) {}
        }

        $stored = Storage::disk('public')->putFile('kyc_documents', $request->file('file'));
        if (!$stored) {
            return response()->json(['status' => 'storage_error', 'message' => "Échec de l'upload du document."], 500);
        }

        $doc->path = 'storage/' . $stored;
        $doc->mime = $request->file('file')->getClientMimeType();
        $doc->size = $request->file('file')->getSize();
        $doc->status = 'uploaded';
        $doc->save();

        return response()->json([
            'id' => (string)$doc->id,
            'type' => $doc->type,
            'url' => $doc->path,
        ], 200);
    }

    public function deleteDocument($id, Request $request)
    {
        $user = $request->user();
        if (!$user) { return response()->json(['message' => 'Unauthenticated'], 401); }
        if ($user->role === 'admin') {
            return response()->json(['status' => 'forbidden', 'message' => 'Les administrateurs ne peuvent pas soumettre un KYC.'], 403);
        }

        $profile = $user->kycProfile;
        if (!$profile) { return response()->json(['status' => 'not_found', 'message' => 'Profil KYC introuvable.'], 404); }

        $doc = $profile->documents()->where('id', $id)->first();
        if (!$doc) { return response()->json(['status' => 'not_found', 'message' => 'Document introuvable.'], 404); }

        if (!empty($doc->path) && str_starts_with($doc->path, 'storage/')) {
            $diskPath = substr($doc->path, strlen('storage/'));
            try { Storage::disk('public')->delete($diskPath); } catch (\Throwable $e) {}
        }
        $doc->delete();

        return response()->json(null, 204);
    }

    public function uploadSelfie(Request $request)
    {
        $user = $request->user();
        if (!$user) { return response()->json(['message' => 'Unauthenticated'], 401); }
        if ($user->role === 'admin') {
            return response()->json(['status' => 'forbidden', 'message' => 'Les administrateurs ne peuvent pas soumettre un KYC.'], 403);
        }

        $request->validate([
            'image' => 'required|image|max:5120|mimes:jpeg,jpg,png',
        ]);

        $profile = $user->kycProfile ?: $user->kycProfile()->create(['status' => 'pending', 'submitted' => false]);

        $stored = Storage::disk('public')->putFile('kyc_documents', $request->file('image'));
        if (!$stored) { return response()->json(['status' => 'storage_error', 'message' => "Échec de l'upload du selfie."], 500); }

        // delete previous selfie if exists
        if (!empty($profile->selfie) && str_starts_with($profile->selfie, 'storage/')) {
            $diskPath = substr($profile->selfie, strlen('storage/'));
            try { Storage::disk('public')->delete($diskPath); } catch (\Throwable $e) {}
        }

        $profile->selfie = 'storage/' . $stored;
        $profile->save();

        return response()->json(['url' => $profile->selfie], 200);
    }

    public function submit(Request $request)
    {
        $user = $request->user();
        if (!$user) { return response()->json(['message' => 'Unauthenticated'], 401); }
        if ($user->role === 'admin') {
            return response()->json(['status' => 'forbidden', 'message' => 'Les administrateurs ne peuvent pas soumettre un KYC.'], 403);
        }

        $profile = $user->kycProfile;
        if (!$profile) { return response()->json(['status' => 'not_found', 'message' => 'Profil KYC introuvable.'], 404); }

        if ($profile->documents()->count() === 0) {
            return response()->json(['status' => 'validation_error', 'message' => 'Aucun document fourni.'], 422);
        }

        // Validate that both sides of the national ID are provided before submission
        $docs = $profile->documents()->get();
        $hasIdFront = $docs->contains(fn($d) => $d->type === 'national_id_front');
        $hasIdBack  = $docs->contains(fn($d) => $d->type === 'national_id_back');

        if ($hasIdFront xor $hasIdBack) {
            return response()->json([
                'status' => 'validation_error',
                'message' => "La carte d'identité doit contenir le recto ET le verso avant la soumission.",
                'missing' => $hasIdFront ? ['national_id_back'] : ['national_id_front'],
            ], 422);
        }

        $profile->submitted = true;
        $profile->status = 'pending';
        $profile->save();

        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new KycSubmittedNotification($user));

        return response()->json(['status' => 'success', 'message' => 'KYC soumis, en attente de validation.']);
    }

    public function decision(Request $request)
    {
        $user = $request->user();
        if (!$user) { return response()->json(['message' => 'Unauthenticated'], 401); }
        $profile = $user->kycProfile;
        if (!$profile) { return response()->json(['status' => 'none']); }

        return response()->json([
            'status' => $profile->status,
            'submitted' => (bool)($profile->submitted ?? false),
            'rejection_reason' => $profile->status === 'rejected' ? ($profile->rejection_reason ?? null) : null,
        ]);
    }

    private function buildRequirements($country = null): array
    {
        // Example requirements; can be adjusted per country
        return [
            'required' => [
                ['id' => 'national_id_front', 'type' => 'national_id_front'],
                ['id' => 'national_id_back', 'type' => 'national_id_back'],
                ['id' => 'proof_of_address', 'type' => 'proof_of_address'],
            ],
            'optional' => [
                ['id' => 'additional_document', 'type' => 'other'],
            ],
        ];
    }
}
