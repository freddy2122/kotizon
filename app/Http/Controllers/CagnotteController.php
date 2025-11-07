<?php

namespace App\Http\Controllers;

use App\Models\Cagnotte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\CagnotteStoreRequest;
use App\Http\Requests\CagnotteUpdateRequest;
use App\Http\Resources\CagnotteResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function adminIndex(Request $request)
    {
        try {
            $request->validate([
                'categorie' => 'nullable|string',
                'published' => 'nullable|boolean',
                'preview' => 'nullable|boolean',
                'user_id' => 'nullable|integer',
                'status' => 'nullable|in:actives,en_revision,terminees,archives,suspendues',
                'q' => 'nullable|string',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = Cagnotte::query()
                ->with('user')
                ->when($request->filled('categorie'), fn($q) => $q->where('categorie', $request->string('categorie')))
                ->when($request->filled('published'), fn($q) => $q->where('est_publiee', (bool)$request->boolean('published')))
                ->when($request->filled('preview'), fn($q) => $q->where('est_previsualisee', (bool)$request->boolean('preview')))
                ->when($request->filled('user_id'), fn($q) => $q->where('user_id', (int)$request->integer('user_id')))
                ->when($request->filled('status'), function ($q) use ($request) {
                    $status = $request->string('status');
                    if ($status === 'en_revision') {
                        $q->where('est_previsualisee', true)->where('est_publiee', false);
                    } elseif ($status === 'actives') {
                        $q->where('est_publiee', true)
                          ->where(function ($qq) {
                              $qq->whereNull('date_limite')->orWhere('date_limite', '>', now());
                          })
                          ->where(function ($qq) {
                              $qq->whereColumn('montant_recolte', '<', 'objectif')
                                 ->orWhereNull('objectif');
                          });
                    } elseif ($status === 'terminees') {
                        $q->where('est_publiee', true)
                          ->where(function ($qq) {
                              $qq->whereColumn('montant_recolte', '>=', 'objectif')
                                 ->orWhere(function ($q3) {
                                     $q3->whereNotNull('date_limite')->where('date_limite', '<=', now());
                                 });
                          });
                    } elseif ($status === 'archives') {
                        $q->where('est_publiee', false)->where('est_previsualisee', false)->where('is_suspended', false);
                    } elseif ($status === 'suspendues') {
                        $q->where('is_suspended', true);
                    }
                })
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . str_replace('%', '\\%', $request->string('q')) . '%';
                    $q->where(function ($qq) use ($term) {
                        $qq->where('titre', 'like', $term)
                           ->orWhere('description', 'like', $term);
                    });
                });

            $perPage = (int) $request->input('per_page', 20);
            $cagnottes = $query->latest()->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $cagnottes,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de lister les cagnottes.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function adminShow($id)
    {
        try {
            $cagnotte = Cagnotte::with('user')->findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $cagnotte,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Cagnotte introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de la cagnotte.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function adminPublish($id)
    {
        try {
            $cagnotte = Cagnotte::findOrFail($id);
            $cagnotte->est_publiee = true;
            $cagnotte->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Cagnotte publiée.',
                'data' => $cagnotte->fresh('user'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Cagnotte introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la publication de la cagnotte.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function adminUnpublish($id)
    {
        try {
            $cagnotte = Cagnotte::findOrFail($id);
            $cagnotte->est_publiee = false;
            $cagnotte->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Cagnotte dépubliée.',
                'data' => $cagnotte->fresh('user'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Cagnotte introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la dépublication de la cagnotte.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function adminDestroy($id)
    {
        try {
            $cagnotte = Cagnotte::findOrFail($id);
            $cagnotte->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Cagnotte supprimée avec succès.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Cagnotte introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de la cagnotte.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function adminSuspend(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);
            $cagnotte = Cagnotte::findOrFail($id);
            $cagnotte->is_suspended = true;
            $cagnotte->suspension_reason = $data['reason'] ?? null;
            $cagnotte->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Cagnotte suspendue.',
                'data' => $cagnotte->fresh('user'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Cagnotte introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suspension de la cagnotte.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function adminUnsuspend($id)
    {
        try {
            $cagnotte = Cagnotte::findOrFail($id);
            $cagnotte->is_suspended = false;
            $cagnotte->suspension_reason = null;
            $cagnotte->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Cagnotte réactivée.',
                'data' => $cagnotte->fresh('user'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Cagnotte introuvable.'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la réactivation de la cagnotte.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function adminExport(Request $request): StreamedResponse
    {
        $request->validate([
            'categorie' => 'nullable|string',
            'published' => 'nullable|boolean',
            'preview' => 'nullable|boolean',
            'user_id' => 'nullable|integer',
            'status' => 'nullable|in:actives,en_revision,terminees,archives,suspendues',
            'q' => 'nullable|string',
        ]);

        $query = Cagnotte::query()
            ->with('user')
            ->when($request->filled('categorie'), fn($q) => $q->where('categorie', $request->string('categorie')))
            ->when($request->filled('published'), fn($q) => $q->where('est_publiee', (bool)$request->boolean('published')))
            ->when($request->filled('preview'), fn($q) => $q->where('est_previsualisee', (bool)$request->boolean('preview')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', (int)$request->integer('user_id')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->string('status');
                if ($status === 'en_revision') {
                    $q->where('est_previsualisee', true)->where('est_publiee', false);
                } elseif ($status === 'actives') {
                    $q->where('est_publiee', true)
                      ->where(function ($qq) {
                          $qq->whereNull('date_limite')->orWhere('date_limite', '>', now());
                      })
                      ->where(function ($qq) {
                          $qq->whereColumn('montant_recolte', '<', 'objectif')
                             ->orWhereNull('objectif');
                      });
                } elseif ($status === 'terminees') {
                    $q->where('est_publiee', true)
                      ->where(function ($qq) {
                          $qq->whereColumn('montant_recolte', '>=', 'objectif')
                             ->orWhere(function ($q3) {
                                 $q3->whereNotNull('date_limite')->where('date_limite', '<=', now());
                             });
                      });
                } elseif ($status === 'archives') {
                    $q->where('est_publiee', false)->where('est_previsualisee', false)->where('is_suspended', false);
                } elseif ($status === 'suspendues') {
                    $q->where('is_suspended', true);
                }
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace('%', '\\%', $request->string('q')) . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('titre', 'like', $term)
                       ->orWhere('description', 'like', $term);
                });
            });

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cagnottes.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Titre', 'Categorie', 'Objectif', 'Collecte', 'Publiee', 'Previsualisee', 'Suspendue', 'User', 'Email', 'Creee', 'MiseAJour']);
            $query->orderByDesc('id')->chunk(1000, function ($rows) use ($handle) {
                foreach ($rows as $c) {
                    fputcsv($handle, [
                        $c->id,
                        $c->titre,
                        $c->categorie,
                        (string) $c->objectif,
                        (string) $c->montant_recolte,
                        $c->est_publiee ? 1 : 0,
                        $c->est_previsualisee ? 1 : 0,
                        $c->is_suspended ? 1 : 0,
                        optional($c->user)->name,
                        optional($c->user)->email,
                        $c->created_at?->toDateTimeString(),
                        $c->updated_at?->toDateTimeString(),
                    ]);
                }
            });
            fclose($handle);
        }, 'cagnottes.csv', $headers);
    }

    public function adminCounters(Request $request)
    {
        try {
            $request->validate([
                'q' => 'nullable|string',
                'categorie' => 'nullable|string',
            ]);

            $base = Cagnotte::query()
                ->when($request->filled('categorie'), fn($q) => $q->where('categorie', $request->string('categorie')))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . str_replace('%', '\\%', $request->string('q')) . '%';
                    $q->where(function ($qq) use ($term) {
                        $qq->where('titre', 'like', $term)
                           ->orWhere('description', 'like', $term);
                    });
                });

            $total = (clone $base)->count();
            $published = (clone $base)->where('est_publiee', true)->count();
            $in_review = (clone $base)->where('est_previsualisee', true)->where('est_publiee', false)->count();
            $suspended = (clone $base)->where('is_suspended', true)->count();
            $archives = (clone $base)->where('est_publiee', false)->where('est_previsualisee', false)->where('is_suspended', false)->count();

            return response()->json([
                'status' => 'success',
                'data' => compact('total', 'published', 'in_review', 'suspended', 'archives'),
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

