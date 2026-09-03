<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sales_draft_id', 'produk_id', 'nama_produk', 'qty', 'harga', 'subtotal'])]
class SalesDraftItem extends Model
{
    public function salesDraft()
    {
        return $this->belongsTo(SalesDraft::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
}
