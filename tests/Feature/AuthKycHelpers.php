<?php

use App\Models\User;
use App\Models\KycProfile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function createUser(array $overrides = []): User
{
    /** @var User $user */
    $user = User::factory()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'is_verified' => true,
        'is_phone_verified' => true,
        'is_active' => true,
    ], $overrides));

    return $user;
}

function approveKyc(User $user): void
{
    $user->kycProfile()->create([
        'national_id' => (string) Str::uuid(),
        'document_type' => 'CNI',
        'document_front' => 'storage/kyc_documents/front.png',
        'document_back' => 'storage/kyc_documents/back.png',
        'status' => 'approved',
    ]);
}

function actingAsSanctum(User $user): void
{
    Sanctum::actingAs($user);
}
