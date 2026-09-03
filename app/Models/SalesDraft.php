<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mitra_id', 'tanggal_order', 'diskon_promo', 'diskon_ongkir', 'ongkir', 'grand_total', 'created_by'])]
class SalesDraft extends Model
{
    protected function casts(): array
    {
        return ['tanggal_order' => 'date'];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function items()
    {
        return $this->hasMany(SalesDraftItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
