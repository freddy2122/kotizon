<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\CagnotteController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;

Route::get('/test', function () {
    return response()->json(['success' => "Accessible"]);
});

// Debug endpoints (protected)
Route::middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::post('/debug/whatsapp', [DebugController::class, 'whatsapp']);
});


Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/verify', [AuthController::class, 'verifyCode'])->middleware('throttle:verify');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:login');
Route::post('/auth/google', [AuthController::class, 'googleLogin'])->middleware('throttle:login');
Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->middleware('throttle:forgot-password');
Route::post('/reset-password', [AuthController::class, 'reset']);

// Cagnottes publiques
Route::get('/cagnottes', [CagnotteController::class, 'index']);
Route::get('/cagnottes/{cagnotte}', [CagnotteController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/kyc', [KycController::class, 'store']);
    Route::get('/kyc/status', [KycController::class, 'status']);
    Route::get('/kyc/requirements', [KycController::class, 'requirements']);
    Route::post('/kyc/documents', [KycController::class, 'uploadDocument']);
    Route::put('/kyc/documents/{id}', [KycController::class, 'replaceDocument']);
    Route::delete('/kyc/documents/{id}', [KycController::class, 'deleteDocument']);
    Route::post('/kyc/selfie', [KycController::class, 'uploadSelfie']);
    Route::post('/kyc/submit', [KycController::class, 'submit']);
    Route::get('/kyc/decision', [KycController::class, 'decision']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/mes-cagnottes', [CagnotteController::class, 'myCagnottes']);
    Route::put('/user/profile', [UserProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Cagnottes
    Route::post('/cagnottes', [CagnotteController::class, 'store']);
    Route::put('/cagnottes/{cagnotte}', [CagnotteController::class, 'update']);
    Route::patch('/cagnottes/{cagnotte}', [CagnotteController::class, 'update']);
    Route::delete('/cagnottes/{cagnotte}', [CagnotteController::class, 'destroy']);
    Route::post('/cagnottes/{cagnotte}/photos', [CagnotteController::class, 'addPhotos']);
    Route::delete('/cagnottes/{cagnotte}/photos', [CagnotteController::class, 'removePhoto']);
    Route::post('/cagnottes/{cagnotte}/publish', [CagnotteController::class, 'publish']);
    Route::post('/cagnottes/{cagnotte}/unpublish', [CagnotteController::class, 'unpublish']);
    Route::post('/cagnottes/{cagnotte}/preview', [CagnotteController::class, 'preview']);
    Route::post('/cagnottes/{cagnotte}/unpreview', [CagnotteController::class, 'unpreview']);
});

Route::middleware(['auth:sanctum', 'can:admin'])->prefix('admin')->group(function () {
    Route::get('kyc-profiles', [KycController::class, 'index']);          // Liste des KYC
    Route::get('kyc-profiles/{id}', [KycController::class, 'show']);      // Détail KYC
    Route::post('kyc-profiles/{id}/validate', [KycController::class, 'validateKyc']);  // Valider
    Route::post('kyc-profiles/{id}/reject', [KycController::class, 'rejectKyc']);      // Rejeter / suspendre
    Route::delete('kyc-profiles/{id}', [KycController::class, 'destroy']);             // Supprimer KYC

    // Users management
    Route::get('users', [UserAdminController::class, 'index']);
    Route::get('users/{id}', [UserAdminController::class, 'show']);
    Route::post('users/{id}/toggle-active', [UserAdminController::class, 'toggleActive']);
    Route::post('users/{id}/role', [UserAdminController::class, 'setRole']);
});

