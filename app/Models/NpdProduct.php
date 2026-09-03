<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['produk_id', 'tanggal_ditandai', 'ditandai_oleh'])]
class NpdProduct extends Model
{
    public const MASA_BERLAKU_HARI = 90;

    protected function casts(): array
    {
        return [
            'tanggal_ditandai' => 'date',
        ];
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function isAktif(): bool
    {
        return $this->tanggal_ditandai->diffInDays(now()) <= self::MASA_BERLAKU_HARI;
    }

    /** Produk IDs currently within their NPD window. */
    public static function activeProdukIds(): array
    {
        return static::where('tanggal_ditandai', '>=', now()->subDays(self::MASA_BERLAKU_HARI))
            ->pluck('produk_id')
            ->all();
    }
}
