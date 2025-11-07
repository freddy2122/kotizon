<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cagnotte()
    {
        return $this->belongsTo(Cagnotte::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
