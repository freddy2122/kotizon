<?php

use App\Models\Cagnotte;
use App\Models\User;
use Database\Factories\CagnotteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

require_once __DIR__ . '/AuthKycHelpers.php';

it('prevents creating cagnotte when KYC not approved', function () {
    $user = createUser();
    actingAsSanctum($user);

    $response = $this->postJson('/api/cagnottes', [
        'titre' => 'Test',
        'categorie' => 'sante',
        'objectif' => 100,
    ]);

    $response->assertStatus(403);
});

it('allows creating cagnotte when KYC approved', function () {
    $user = createUser();
    approveKyc($user);
    actingAsSanctum($user);

    $response = $this->postJson('/api/cagnottes', [
        'titre' => 'Test',
        'categorie' => 'sante',
        'objectif' => 100,
    ]);

    $response->assertStatus(200)->assertJsonPath('message', 'Cagnotte créée avec succès.');
});

it('lists only published cagnottes on public index', function () {
    $u1 = createUser();
    $u2 = createUser();
    approveKyc($u1);
    approveKyc($u2);

    Cagnotte::factory()->for($u1)->create(['est_publiee' => true]);
    Cagnotte::factory()->for($u2)->create(['est_publiee' => false]);

    $this->getJson('/api/cagnottes')
        ->assertStatus(200)
        ->assertJsonMissingPath('data.0.est_publiee', false);
});

it('owner can publish, unpublish, preview, unpreview', function () {
    $user = createUser();
    approveKyc($user);
    actingAsSanctum($user);

    $cagnotte = Cagnotte::factory()->for($user)->create([
        'titre' => 't', 'categorie' => 'sante', 'objectif' => 10
    ]);

    $this->postJson("/api/cagnottes/{$cagnotte->id}/preview")
        ->assertOk();
    expect($cagnotte->fresh()->est_previsualisee)->toBeTrue();

    $this->postJson("/api/cagnottes/{$cagnotte->id}/publish")
        ->assertOk();
    expect($cagnotte->fresh()->est_publiee)->toBeTrue();

    $this->postJson("/api/cagnottes/{$cagnotte->id}/unpreview")
        ->assertOk();
    expect($cagnotte->fresh()->est_previsualisee)->toBeFalse();

    $this->postJson("/api/cagnottes/{$cagnotte->id}/unpublish")
        ->assertOk();
    expect($cagnotte->fresh()->est_publiee)->toBeFalse();
});

it('non owner cannot update or delete cagnotte', function () {
    $owner = createUser();
    approveKyc($owner);
    $other = createUser();
    actingAsSanctum($other);

    $cagnotte = Cagnotte::factory()->for($owner)->create([
        'titre' => 't', 'categorie' => 'sante', 'objectif' => 10
    ]);

    $this->putJson("/api/cagnottes/{$cagnotte->id}", ['titre' => 'x'])
        ->assertStatus(403);

    $this->deleteJson("/api/cagnottes/{$cagnotte->id}")
        ->assertStatus(403);
});

it('my cagnottes endpoint lists user items with filters', function () {
    $user = createUser();
    approveKyc($user);
    actingAsSanctum($user);

    Cagnotte::factory()->for($user)->create(['titre' => 'abc', 'categorie' => 'sante', 'objectif' => 10, 'est_publiee' => true]);
    Cagnotte::factory()->for($user)->create(['titre' => 'xyz', 'categorie' => 'projet', 'objectif' => 10, 'est_publiee' => false, 'est_previsualisee' => true]);

    $this->getJson('/api/mes-cagnottes?categorie=sante&q=ab')
        ->assertOk()
        ->assertJsonFragment(['categorie' => 'sante'])
        ->assertJsonMissing(['categorie' => 'projet']);
});

it('owner can add and remove photos with storage fake', function () {
    Storage::fake('public');

    $user = createUser();
    approveKyc($user);
    actingAsSanctum($user);

    $cagnotte = Cagnotte::factory()->for($user)->create([
        'titre' => 't', 'categorie' => 'sante', 'objectif' => 10
    ]);

    $file1 = UploadedFile::fake()->image('p1.jpg', 600, 400);
    $file2 = UploadedFile::fake()->image('p2.png', 800, 600);

    $resp = $this->post('/api/cagnottes/'.$cagnotte->id.'/photos', [
        'photos' => [$file1, $file2]
    ]);

    $resp->assertOk();
    $photos = $resp->json('photos');
    expect($photos)->toBeArray()->and(count($photos))->toBe(2);

    // Les chemins commencent par storage/
    expect($photos[0])->toStartWith('storage/');
    // Vérifier la présence des fichiers sur le disque public
    $rel0 = substr($photos[0], strlen('storage/'));
    $rel1 = substr($photos[1], strlen('storage/'));
    Storage::disk('public')->assertExists($rel0);
    Storage::disk('public')->assertExists($rel1);

    // Remove one photo
    $del = $this->deleteJson('/api/cagnottes/'.$cagnotte->id.'/photos', [
        'path' => $photos[0]
    ]);
    $del->assertOk();
    $after = $del->json('photos');
    expect($after)->not->toContain($photos[0]);
    Storage::disk('public')->assertMissing($rel0);
});

it('public index supports advanced filters categorie and q', function () {
    $u = createUser();
    approveKyc($u);

    // publiées
    Cagnotte::factory()->for($u)->create(['titre' => 'Opération coeur', 'categorie' => 'sante', 'objectif' => 200, 'est_publiee' => true]);
    Cagnotte::factory()->for($u)->create(['titre' => 'Projet scolaire', 'categorie' => 'projet', 'objectif' => 300, 'est_publiee' => true]);
    // non publiée
    Cagnotte::factory()->for($u)->create(['titre' => 'Urgence toit', 'categorie' => 'urgence', 'objectif' => 150, 'est_publiee' => false]);

    // Filtre categorie=sante et q=coeur
    $res = $this->getJson('/api/cagnottes?categorie=sante&q=coeur');
    $res->assertOk();
    $json = $res->json();
    // Doit contenir le titre 'Opération coeur' et pas 'Projet scolaire'
    $titles = array_map(fn($i) => $i['titre'], $json['data']);
    expect($titles)->toContain('Opération coeur');
    expect($titles)->not->toContain('Projet scolaire');
});
