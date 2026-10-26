<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['produk_id', 'tanggal_mulai_campaign', 'ditandai_oleh'])]
class TrendSku extends Model
{
    protected $casts = [
        'tanggal_mulai_campaign' => 'date',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function ditandaiOleh()
    {
        return $this->belongsTo(User::class, 'ditandai_oleh');
    }
}
