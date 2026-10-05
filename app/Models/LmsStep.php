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
    public const PLATFORMS = ['shopee' => 'Shopee', 'meta' => 'Meta', 'tiktok' => 'TikTok', 'wa_sales_machine' => 'WA Sales Machine'];

    /**
     * % video selesai minimal biar status LMS mitra per platform dianggap
     * "Lengkap" — dipakai bareng-bareng di SetUpLmsController (status per
     * baris), KomitTrackerController & StandInLineController (roster
     * "sudah Lengkap LMS-nya di minimal satu platform"), satu tempat biar
     * gak ketinggalan kalau diubah lagi.
     */
    public const LENGKAP_THRESHOLD_PCT = 80;

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function completions()
    {
        return $this->hasMany(LmsStepCompletion::class, 'lms_step_id');
    }

    public function scopeAktifUntuk($query, string $platform)
    {
        return $query->where('platform', $platform)->where('aktif', true)->orderBy('urutan');
    }
}
