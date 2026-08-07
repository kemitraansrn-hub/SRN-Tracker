<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kode_sku', 'nama', 'brand', 'kategori', 'harga', 'status'])]
class Produk extends Model
{
    protected $table = 'produk';

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
