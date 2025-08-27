<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cagnottes', function (Blueprint $table) {
            $table->id();

            // Lien avec l'utilisateur
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Étapes de création
            $table->string('categorie'); // santé, urgence, funérailles, projet, etc.
            $table->string('titre');
            $table->decimal('objectif', 12, 2); // Montant cible
            $table->text('description')->nullable();
            $table->json('photos')->nullable(); // stockage en tableau JSON des noms de fichiers
            $table->text('modele_texte')->nullable(); // optionnel, utilisé comme aide à la rédaction

            // Prévisualisation / état de publication
            $table->boolean('est_previsualisee')->default(false);
            $table->boolean('est_publiee')->default(false);

            // Suivi
            $table->decimal('montant_recolte', 12, 2)->default(0);
            $table->timestamp('date_limite')->nullable(); // Si la cagnotte a une durée

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cagnottes');
    }
};
