<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CagnotteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'categorie' => $this->categorie,
            'titre' => $this->titre,
            'objectif' => (string) $this->objectif,
            'description' => $this->description,
            'photos' => $this->photos ?? [],
            'modele_texte' => $this->modele_texte,
            'est_previsualisee' => (bool) $this->est_previsualisee,
            'est_publiee' => (bool) $this->est_publiee,
            'montant_recolte' => (string) $this->montant_recolte,
            'date_limite' => $this->date_limite?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => $this->whenLoaded('user', function() {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar' => $this->user->avatar,
                ];
            }),
        ];
    }
}
