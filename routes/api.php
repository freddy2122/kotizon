<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\CagnotteController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\CagnotteRequestController;
use App\Http\Controllers\Admin\WithdrawalAdminController;
use App\Http\Controllers\Admin\ReportAdminController;
use App\Http\Controllers\Admin\CagnotteRequestAdminController;
use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\AnalyticsAdminController;
use App\Http\Controllers\UserDashboardController;
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
    Route::post('/user/change-password', [UserProfileController::class, 'changePassword']);
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

    // Demandes de création de cagnottes (utilisateur)
    Route::post('/cagnotte-requests', [CagnotteRequestController::class, 'store']);

    // Dashboard utilisateur
    Route::get('/user/dashboard', [UserDashboardController::class, 'dashboard']);
    Route::get('/user/donations', [UserDashboardController::class, 'donations']);
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

    Route::get('overview', [UserAdminController::class, 'overview']);
    Route::get('users/counters', [UserAdminController::class, 'counters']);

    // Categories (CRUD)
    Route::get('categories', [CategoryAdminController::class, 'index']);
    Route::post('categories', [CategoryAdminController::class, 'store']);
    Route::put('categories/{id}', [CategoryAdminController::class, 'update']);
    Route::delete('categories/{id}', [CategoryAdminController::class, 'destroy']);

    Route::get('cagnottes', [CagnotteController::class, 'adminIndex']);
    Route::get('cagnottes/export', [CagnotteController::class, 'adminExport']);
    Route::get('cagnottes/{id}', [CagnotteController::class, 'adminShow']);
    Route::post('cagnottes/{id}/publish', [CagnotteController::class, 'adminPublish']);
    Route::post('cagnottes/{id}/unpublish', [CagnotteController::class, 'adminUnpublish']);
    Route::post('cagnottes/{id}/suspend', [CagnotteController::class, 'adminSuspend']);
    Route::post('cagnottes/{id}/unsuspend', [CagnotteController::class, 'adminUnsuspend']);
    Route::delete('cagnottes/{id}', [CagnotteController::class, 'adminDestroy']);
    Route::get('cagnottes/counters', [CagnotteController::class, 'adminCounters']);

    // Withdrawals (Retraits)
    Route::get('withdrawals', [WithdrawalAdminController::class, 'index']);
    Route::get('withdrawals/{id}', [WithdrawalAdminController::class, 'show']);
    Route::post('withdrawals/{id}/approve', [WithdrawalAdminController::class, 'approve']);
    Route::post('withdrawals/{id}/reject', [WithdrawalAdminController::class, 'reject']);

    // Reports (Signalements)
    Route::get('reports', [ReportAdminController::class, 'index']);
    Route::get('reports/{id}', [ReportAdminController::class, 'show']);
    Route::post('reports/{id}/resolve', [ReportAdminController::class, 'resolve']);

    // Demandes de création de cagnottes (admin)
    Route::get('cagnotte-requests', [CagnotteRequestAdminController::class, 'index']);
    Route::get('cagnotte-requests/{id}', [CagnotteRequestAdminController::class, 'show']);
    Route::post('cagnotte-requests/{id}/approve', [CagnotteRequestAdminController::class, 'approve']);
    Route::post('cagnotte-requests/{id}/reject', [CagnotteRequestAdminController::class, 'reject']);

    // Analytics (90 jours)
    Route::get('analytics/donations', [AnalyticsAdminController::class, 'donations']);
    Route::get('analytics/withdrawals', [AnalyticsAdminController::class, 'withdrawals']);
});

