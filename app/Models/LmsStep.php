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

    /**
     * Mitra id yang LMS-nya udah "Lengkap" (>= LENGKAP_THRESHOLD_PCT video
     * selesai) di MINIMAL SATU platform aktif — satu-satunya tempat logika
     * ini dihitung (sebelumnya ditulis ulang di KomitTrackerController &
     * StandInLineController, dan sekali kena bug karena beda penulisan —
     * lihat catatan Eloquent Collection::only() di memory). Dipakai juga
     * buat nge-gate upload Tracking Performance (gak boleh masuk kalau LMS
     * mitranya belum Lengkap).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function mitraIdsLengkap(): \Illuminate\Support\Collection
    {
        $stepCountByPlatform = self::where('aktif', true)
            ->select('platform', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('platform')->pluck('total', 'platform');

        return \Illuminate\Support\Facades\DB::table('lms_step_completions')
            ->join('lms_steps', 'lms_steps.id', '=', 'lms_step_completions.lms_step_id')
            ->where('lms_steps.aktif', true)
            ->groupBy('lms_step_completions.mitra_id', 'lms_steps.platform')
            ->selectRaw('lms_step_completions.mitra_id, lms_steps.platform, count(*) as done')
            ->get()
            ->filter(fn ($row) => ($stepCountByPlatform[$row->platform] ?? 0) > 0 && $row->done >= $stepCountByPlatform[$row->platform] * self::LENGKAP_THRESHOLD_PCT / 100)
            ->pluck('mitra_id')
            ->unique()
            ->values();
    }
}
