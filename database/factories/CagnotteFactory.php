<?php

namespace Database\Factories;

use App\Models\Cagnotte;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cagnotte>
 */
class CagnotteFactory extends Factory
{
    protected $model = Cagnotte::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'categorie' => fake()->randomElement(['sante', 'urgence', 'projet', 'funerailles']),
            'titre' => fake()->sentence(4),
            'objectif' => fake()->randomFloat(2, 10, 5000),
            'description' => fake()->paragraph(),
            'photos' => [],
            'modele_texte' => null,
            'est_previsualisee' => false,
            'est_publiee' => false,
            'montant_recolte' => 0,
            'date_limite' => null,
        ];
    }
}
