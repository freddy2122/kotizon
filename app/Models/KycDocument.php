<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycDocument extends Model
{
    protected $guarded = ['id'];

    public function profile()
    {
        return $this->belongsTo(KycProfile::class, 'kyc_profile_id');
    }
}
