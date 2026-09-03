<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kode_sku', 'nama', 'brand', 'kategori', 'harga', 'qty_per_poin', 'status', 'auto_created', 'reviewed_at'])]
class Produk extends Model
{
    protected $table = 'produk';

    protected function casts(): array
    {
        return [
            'auto_created' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Produk yang ke-create otomatis pas import order harian dan belum di-review admin. */
    public function scopePendingReview($query)
    {
        return $query->where('auto_created', true)->whereNull('reviewed_at');
    }
}
