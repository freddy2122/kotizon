<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cagnottes()
    {
        return $this->hasMany(Cagnotte::class, 'categorie', 'key');
    }
}
