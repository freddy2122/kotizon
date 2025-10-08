<?php

namespace App\Http\Controllers;

use App\Models\Cagnotte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\CagnotteStoreRequest;
use App\Http\Requests\CagnotteUpdateRequest;
use App\Http\Resources\CagnotteResource;

class CagnotteController extends Controller
{
    public function index(Request $request)
    {
        $cagnottes = Cagnotte::query()
            ->with('user')
            ->where('est_publiee', true)
            ->when($request->filled('categorie'), function ($q) use ($request) {
                $q->where('categorie', $request->string('categorie'));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace('%', '\\%', $request->string('q')) . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('titre', 'like', $term)
                       ->orWhere('description', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20);

        return CagnotteResource::collection($cagnottes);
    }

    public function myCagnottes(Request $request)
    {
        $user = $request->user();
        $cagnottes = Cagnotte::query()
            ->where('user_id', $user->id)
            ->when($request->filled('categorie'), function ($q) use ($request) {
                $q->where('categorie', $request->string('categorie'));
            })
            ->when($request->filled('published'), function ($q) use ($request) {
                $q->where('est_publiee', filter_var($request->string('published'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('preview'), function ($q) use ($request) {
                $q->where('est_previsualisee', filter_var($request->string('preview'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace('%', '\\%', $request->string('q')) . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('titre', 'like', $term)
                       ->orWhere('description', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20);

        return CagnotteResource::collection($cagnottes);
    }

    public function show(Cagnotte $cagnotte)
    {
        $cagnotte->load('user');
        return new CagnotteResource($cagnotte);
    }

    public function store(CagnotteStoreRequest $request)
    {
        $user = Auth::user();

        // Conditions : email vérifié + KYC approuvé
        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Votre email n\'est pas vérifié.'], 403);
        }

        if (!$user->kycProfile || $user->kycProfile->status !== 'approved') {
            return response()->json(['message' => 'Votre profil KYC n\'est pas encore approuvé.'], 403);
        }

        $validated = $request->validated();

        $validated['user_id'] = $user->id;

        // Gestion des uploads de photos
        if ($request->hasFile('photos')) {
            $paths = [];
            foreach ($request->file('photos') as $file) {
                if ($file) {
                    $stored = Storage::disk('public')->putFile('cagnottes', $file);
                    $paths[] = 'storage/' . $stored;
                }
            }
            $validated['photos'] = $paths;
        }

        $cagnotte = Cagnotte::create($validated);
        $cagnotte->load('user');

        return (new CagnotteResource($cagnotte))
            ->additional(['message' => 'Cagnotte créée avec succès.']);
    }

    public function update(CagnotteUpdateRequest $request, Cagnotte $cagnotte)
    {
        $this->authorize('update', $cagnotte);

        $data = $request->validated();
        $cagnotte->fill($data);
        $cagnotte->save();
        $cagnotte->refresh()->load('user');

        return new CagnotteResource($cagnotte);
    }

    public function destroy(Request $request, Cagnotte $cagnotte)
    {
        $this->authorize('delete', $cagnotte);
        $cagnotte->delete();
        return response()->json(['message' => 'Cagnotte supprimée avec succès.']);
    }

    public function addPhotos(Request $request, Cagnotte $cagnotte)
    {
        $this->authorize('addPhotos', $cagnotte);
        $request->validate([
            'photos' => 'required|array',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $existing = $cagnotte->photos ?? [];
        foreach ($request->file('photos') as $file) {
            $stored = Storage::disk('public')->putFile('cagnottes', $file);
            $existing[] = 'storage/' . $stored;
        }
        $cagnotte->photos = $existing;
        $cagnotte->save();

        return new CagnotteResource($cagnotte->fresh('user'));
    }

    public function removePhoto(Request $request, Cagnotte $cagnotte)
    {
        $this->authorize('removePhoto', $cagnotte);
        $request->validate([
            'path' => 'required|string',
        ]);

        $photos = $cagnotte->photos ?? [];
        $updated = array_values(array_filter($photos, fn($p) => $p !== $request->path));

        // Optionnel: supprimer le fichier du disque si on est sûr que plus personne ne l'utilise
        if (str_starts_with($request->path, 'storage/')) {
            $relative = substr($request->path, strlen('storage/'));
            Storage::disk('public')->delete($relative);
        }

        $cagnotte->photos = $updated;
        $cagnotte->save();

        return new CagnotteResource($cagnotte->fresh('user'));
    }

    public function publish(Request $request, Cagnotte $cagnotte)
    {
        $this->authorize('publish', $cagnotte);

        $user = $request->user();
        if (!$user || !$user->email_verified_at) {
            return response()->json(['message' => 'Email non vérifié.'], 403);
        }
        if (!$user->kycProfile || $user->kycProfile->status !== 'approved') {
            return response()->json(['message' => 'Votre profil KYC n\'est pas encore approuvé.'], 403);
        }

        // Règles de publication minimales (ex: titre, categorie, objectif > 0)
        if (!$cagnotte->titre || !$cagnotte->categorie || (float) $cagnotte->objectif <= 0) {
            return response()->json(['message' => 'Données incomplètes pour publier la cagnotte.'], 422);
        }

        $cagnotte->est_publiee = true;
        $cagnotte->save();

        return new CagnotteResource($cagnotte->fresh('user'));
    }

    public function unpublish(Request $request, Cagnotte $cagnotte)
    {
        $this->authorize('unpublish', $cagnotte);
        $cagnotte->est_publiee = false;
        $cagnotte->save();
        return new CagnotteResource($cagnotte->fresh('user'));
    }

    public function preview(Request $request, Cagnotte $cagnotte)
    {
        $this->authorize('preview', $cagnotte);
        $cagnotte->est_previsualisee = true;
        $cagnotte->save();
        return new CagnotteResource($cagnotte->fresh('user'));
    }

    public function unpreview(Request $request, Cagnotte $cagnotte)
    {
        $this->authorize('unpreview', $cagnotte);
        $cagnotte->est_previsualisee = false;
        $cagnotte->save();
        return new CagnotteResource($cagnotte->fresh('user'));
    }
}

