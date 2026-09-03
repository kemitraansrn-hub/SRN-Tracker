<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['buyback_request_id', 'produk_id', 'nama_produk', 'qty', 'harga', 'subtotal', 'tanggal_ed', 'umur_produk_bulan', 'nilai_buyback'])]
class BuybackRequestItem extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_ed' => 'date',
        ];
    }

    public function buybackRequest()
    {
        return $this->belongsTo(BuybackRequest::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
}
