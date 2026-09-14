<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kolom manual + hasil rumus buat 1 baris "Master Database" modul Growth
 * Specialist (sheet "Kartu Profil Mitra"). Kolom hasil rumus (Toleransi
 * Cashflow, Tipe Mitra, Deadline Closing Pertama, % Operational Cost)
 * sengaja TIDAK disimpan sebagai kolom sendiri — selalu dihitung ulang dari
 * kolom sumbernya di bawah, sama seperti ARRAYFORMULA di sheet aslinya,
 * biar gak ada nilai basi kalau salah satu kolom sumber diubah belakangan.
 */
#[Fillable([
    'mitra_id', 'status', 'tanggal_onboarding', 'modal_bisnis', 'modal_srn', 'cost',
    'tim_sendiri', 'platform_jualan', 'jam_aktif', 'tipe_channel',
    'channel_fokus_1', 'channel_fokus_2', 'motivasi', 'kemampuan', 'keaktifan',
    'deadline_setup_channel', 'target_traffic', 'target_leads', 'lms_status', 'catatan',
])]
class MitraProfilGrowth extends Model
{
    protected $table = 'mitra_profil_growth';

    protected function casts(): array
    {
        return [
            'tanggal_onboarding' => 'date',
            'deadline_setup_channel' => 'date',
            'platform_jualan' => 'array',
            'channel_fokus_1' => 'array',
            'channel_fokus_2' => 'array',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    /**
     * Rasio Beban = (Operasional + Marketing) ÷ Modal SRN × 100% — null
     * kalau cost atau modal SRN belum diisi (tidak tebak-tebakan, sama
     * seperti indikasi di sheet "Sistem Skor").
     */
    public function persenOperationalCost(): ?float
    {
        if ($this->cost === null || ! $this->modal_srn) {
            return null;
        }

        return round((float) $this->cost / (float) $this->modal_srn * 100, 1);
    }

    /**
     * Fleksibel: <30% · Sedang: 31–50% · Ketat: >50% dari Rasio Beban.
     */
    public function toleransiCashflow(): ?string
    {
        $pct = $this->persenOperationalCost();

        if ($pct === null) {
            return null;
        }

        return match (true) {
            $pct < 30 => 'Fleksibel',
            $pct <= 50 => 'Sedang',
            default => 'Ketat',
        };
    }

    /**
     * Skor total maks 25 poin (Modal 1–5 + Cashflow 1–3 + Tim 0–2 +
     * Klasifikasi 3–15) — Prioritas kalau >=16, Standar kalau <16. Null
     * (bukan nebak "Standar") kalau data Finansial ATAU Klasifikasi belum
     * lengkap diisi, sama seperti aturan di sheet aslinya.
     */
    public function tipeMitra(): ?string
    {
        $toleransiCashflow = $this->toleransiCashflow();

        if ($this->modal_bisnis === null || $toleransiCashflow === null || $this->tim_sendiri === null
            || $this->motivasi === null || $this->kemampuan === null || $this->keaktifan === null) {
            return null;
        }

        $skorModal = match (true) {
            $this->modal_bisnis >= 100_000_000 => 5,
            $this->modal_bisnis >= 50_000_000 => 4,
            $this->modal_bisnis >= 25_000_000 => 3,
            $this->modal_bisnis >= 10_000_000 => 2,
            default => 1,
        };

        $skorCashflow = match ($toleransiCashflow) {
            'Fleksibel' => 3,
            'Sedang' => 2,
            default => 1,
        };

        $skorTim = match (true) {
            str_contains(mb_strtolower($this->tim_sendiri), 'besar') => 2,
            str_contains(mb_strtolower($this->tim_sendiri), 'tim') => 1,
            default => 0,
        };

        $skorKlasifikasi = $this->motivasi + $this->kemampuan + $this->keaktifan;

        $total = $skorModal + $skorCashflow + $skorTim + $skorKlasifikasi;

        return $total >= 16 ? 'Prioritas' : 'Standar';
    }

    /**
     * Deadline Closing Pertama = Deadline Setup Channel + 7 hari.
     */
    public function deadlineClosingPertama(): ?\Illuminate\Support\Carbon
    {
        return $this->deadline_setup_channel?->copy()->addDays(7);
    }
}
