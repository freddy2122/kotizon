<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CagnotteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => 'sometimes|string|max:255',
            'categorie' => 'sometimes|string|max:255',
            'objectif' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'modele_texte' => 'nullable|string',
        ];
    }
}
