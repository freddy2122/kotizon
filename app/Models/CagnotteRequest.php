<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CagnotteRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'objectif_propose' => 'decimal:2',
        'approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
