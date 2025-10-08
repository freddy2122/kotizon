<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CagnotteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth handled in routes/middleware
    }

    public function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'categorie' => 'required|string|max:255',
            'objectif' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
            'modele_texte' => 'nullable|string',
        ];
    }
}
