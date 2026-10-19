<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'bank', 'no_rekening', 'alamat', 'created_by'])]
class Pengakuisisi extends Model
{
    public function akuisisiReferrals()
    {
        return $this->hasMany(AkuisisiReferral::class);
    }
}
