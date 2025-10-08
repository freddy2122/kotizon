<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycProfile extends Model
{

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'status' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(KycDocument::class);
    }
}
