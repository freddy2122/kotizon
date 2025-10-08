<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UserProfileController extends Controller
{
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'unauthenticated',
                    'message' => 'Utilisateur non authentifié.'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'name'          => 'string|max:255',
                'phone'         => 'nullable|string|max:20',
                'gender'        => 'nullable|in:male,female,other',
                'date_of_birth' => 'nullable|date',
                'address'       => 'nullable|string|max:255',
                'city'          => 'nullable|string|max:100',
                'country'       => 'nullable|string|max:100',
                'avatar'        => 'nullable|image|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'validation_error',
                    'message' => 'Les données fournies sont invalides.',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Handle avatar upload if present
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');

                // Store new avatar
                $path = Storage::disk('public')->putFile('avatars', $file);
                if (!$path) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'storage_error',
                        'message' => "Échec de l'enregistrement de l'avatar."
                    ], 500);
                }

                // Delete old avatar if exists and was stored on the public disk
                if (!empty($user->avatar)) {
                    $oldPath = $user->avatar;
                    // our app stores path like 'storage/avatars/..', convert to disk path 'avatars/...'
                    if (str_starts_with($oldPath, 'storage/')) {
                        $diskPath = substr($oldPath, strlen('storage/'));
                        try {
                            Storage::disk('public')->delete($diskPath);
                        } catch (\Throwable $e) {
                            // Log silently; do not fail the whole request for delete failure
                            // logger()->warning('Avatar delete failed', ['error' => $e->getMessage(), 'path' => $diskPath]);
                        }
                    }
                }

                $user->avatar = 'storage/' . $path;
            }

            // Fill other attributes
            $user->fill($request->only([
                'name',
                'phone',
                'gender',
                'date_of_birth',
                'address',
                'city',
                'country'
            ]));

            $user->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Profil mis à jour avec succès.',
                'user' => $user
            ]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour du profil.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

