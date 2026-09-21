<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Master video/langkah LMS per platform (Shopee, Meta, TikTok). Disimpan di
 * tabel, bukan konstanta, karena daftarnya nanti dikelola admin.
 */
#[Fillable(['platform', 'urutan', 'judul', 'aktif'])]
class LmsStep extends Model
{
    public const PLATFORMS = ['shopee' => 'Shopee', 'meta' => 'Meta', 'tiktok' => 'TikTok'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function scopeAktifUntuk($query, string $platform)
    {
        return $query->where('platform', $platform)->where('aktif', true)->orderBy('urutan');
    }
}
