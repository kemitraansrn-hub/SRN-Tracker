<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ar_receivable_id', 'cicilan_ke', 'jumlah_bayar', 'tanggal_bayar', 'bukti_bayar', 'created_by'])]
class ArPayment extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_bayar' => 'date',
        ];
    }

    public function arReceivable()
    {
        return $this->belongsTo(ArReceivable::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function buktiBayarUrl(): ?string
    {
        return $this->bukti_bayar ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->bukti_bayar) : null;
    }
}
