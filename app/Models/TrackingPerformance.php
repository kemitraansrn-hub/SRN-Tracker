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

    public function periodeLabel(): string
    {
        return $this->tanggal_mulai->equalTo($this->tanggal_selesai)
            ? $this->tanggal_mulai->format('d/m/Y')
            : $this->tanggal_mulai->format('d/m/Y').' - '.$this->tanggal_selesai->format('d/m/Y');
    }
}
