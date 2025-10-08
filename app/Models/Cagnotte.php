<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cagnotte extends Model
{
        protected $guarded = ['id'];

    protected $casts = [
        'photos' => 'array',
        'est_previsualisee' => 'boolean',
        'est_publiee' => 'boolean',
        'objectif' => 'decimal:2',
        'montant_recolte' => 'decimal:2',
        'date_limite' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
