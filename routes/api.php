<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify', [AuthController::class, 'verifyCode']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [AuthController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/kyc', [KycController::class, 'store']);
    Route::get('/kyc/status', [KycController::class, 'status']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/user/profile', [UserProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'can:admin'])->prefix('admin')->group(function () {
    Route::get('kyc-profiles', [KycController::class, 'index']);          // Liste des KYC
    Route::get('kyc-profiles/{id}', [KycController::class, 'show']);      // Détail KYC
    Route::post('kyc-profiles/{id}/validate', [KycController::class, 'validateKyc']);  // Valider
    Route::post('kyc-profiles/{id}/reject', [KycController::class, 'rejectKyc']);      // Rejeter / suspendre
    Route::delete('kyc-profiles/{id}', [KycController::class, 'destroy']);             // Supprimer KYC
});
