<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'price_adjustment_request_id', 'produk_id', 'nama_produk_manual', 'harga_het', 'harga_diskon', 'link_etalase',
])]
class PriceAdjustmentItem extends Model
{
    protected function casts(): array
    {
        return [
            'harga_het' => 'decimal:2',
            'harga_diskon' => 'decimal:2',
        ];
    }

    public function priceAdjustmentRequest()
    {
        return $this->belongsTo(PriceAdjustmentRequest::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function namaProdukTampil(): string
    {
        return $this->produk->nama ?? $this->nama_produk_manual ?? '—';
    }

    public function persentaseDiskon(): ?float
    {
        if ((float) $this->harga_het <= 0) {
            return null;
        }

        return round(((float) $this->harga_het - (float) $this->harga_diskon) / (float) $this->harga_het * 100, 2);
    }
}
