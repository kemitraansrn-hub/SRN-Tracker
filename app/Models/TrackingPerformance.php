<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = performa toko satu mitra dalam satu periode (biasanya satu
 * minggu) dari file upload Tracking Performance. Angka mentah yang
 * disimpan; CTR, CVR, dan ROAS SELALU dihitung ulang dari angka mentah
 * lewat method di bawah (bukan kolom), jadi rumusnya cukup diubah di sini.
 */
#[Fillable([
    'mitra_id', 'week', 'kuartal', 'tanggal_mulai', 'tanggal_selesai', 'gmv',
    'total_pesanan', 'produk_diklik', 'total_pengunjung', 'ads_spend', 'uploaded_by',
])]
class TrackingPerformance extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'gmv' => 'float',
            'ads_spend' => 'float',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    /** CTR (%) = Produk Diklik / Total Pengunjung. */
    public function ctr(): ?float
    {
        return $this->total_pengunjung > 0 ? round($this->produk_diklik / $this->total_pengunjung * 100, 2) : null;
    }

    /** CVR (%) = Total Pesanan / Produk Diklik. */
    public function cvr(): ?float
    {
        return $this->produk_diklik > 0 ? round($this->total_pesanan / $this->produk_diklik * 100, 2) : null;
    }

    /** ROAS = GMV / Ads Spend; 0 kalau tidak pakai iklan (Ads Spend kosong/0). */
    public function roas(): float
    {
        return $this->ads_spend > 0 ? round($this->gmv / $this->ads_spend, 2) : 0.0;
    }

    /** Metrik yang dibandingkan dengan periode sebelumnya di kolom Δ. */
    public const METRIK_DELTA = ['gmv', 'traffic', 'ctr', 'cvr'];

    private function nilaiMetrik(string $metrik): float
    {
        return match ($metrik) {
            'gmv' => (float) $this->gmv,
            'traffic' => (float) $this->total_pengunjung,
            'ctr' => $this->ctr() ?? 0.0,
            'cvr' => $this->cvr() ?? 0.0,
        };
    }

    /**
     * Arah perubahan satu metrik dibanding periode sebelumnya milik mitra
     * yang sama: 'naik' | 'turun' | 'sama'; null kalau belum ada pembanding
     * (upload pertama mitra ini = baseline). CTR/CVR dibandingkan dengan
     * pembulatan 2 desimal yang sama dengan yang tampil di tabel.
     */
    public function arah(string $metrik, ?self $sebelumnya): ?string
    {
        if (! $sebelumnya) {
            return null;
        }

        $sekarang = round($this->nilaiMetrik($metrik), 2);
        $lalu = round($sebelumnya->nilaiMetrik($metrik), 2);

        return $sekarang > $lalu ? 'naik' : ($sekarang < $lalu ? 'turun' : 'sama');
    }

    /**
     * Status kolom Growth: 'Baseline' | 'Growth' | 'Stagnan' | 'Turun'.
     * Baseline = belum ada periode sebelumnya. Selain itu, KPI-nya dari
     * pertumbuhan GMV terhadap periode sebelumnya milik mitra yang sama:
     * >= 5% Growth, 0% s/d 4,9% Stagnan, < 0% Turun (aturan dari user,
     * 2026-09-22). Kalau GMV periode sebelumnya 0, persen gak terhitung
     * (pembagi nol) — dianggap Growth kalau GMV sekarang > 0, Stagnan
     * kalau tetap 0 (gak ada perubahan berarti).
     */
    public function statusGrowth(?self $sebelumnya): ?string
    {
        if (! $sebelumnya) {
            return 'Baseline';
        }

        if ((float) $sebelumnya->gmv <= 0.0) {
            return $this->gmv > 0 ? 'Growth' : 'Stagnan';
        }

        $persenGmv = ($this->gmv - $sebelumnya->gmv) / $sebelumnya->gmv * 100;

        return match (true) {
            $persenGmv >= 5 => 'Growth',
            $persenGmv >= 0 => 'Stagnan',
            default => 'Turun',
        };
    }

    public function periodeLabel(): string
    {
        return $this->tanggal_mulai->equalTo($this->tanggal_selesai)
            ? $this->tanggal_mulai->format('d/m/Y')
            : $this->tanggal_mulai->format('d/m/Y').' - '.$this->tanggal_selesai->format('d/m/Y');
    }
}
