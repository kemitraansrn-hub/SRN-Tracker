<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu mitra hasil "Program Akuisisi Mitra Offline" (lihat dokumen program,
 * 2026-10-01) -- dikaitkan ke satu Pengakuisisi sejak tanggal_akuisisi.
 * Pengakuisisi adalah pihak di luar sistem, gak punya akun login (user
 * eksplisit mengoreksi ini 2026-10-16, versi awal sempat pakai role user
 * baru sebelum dikoreksi) -- sekarang jadi master data sendiri (tabel
 * `pengakuisisis`, punya Bank/No Rekening/Alamat buat pembayaran bonus,
 * 2026-10-19) yang dipilih lewat pengakuisisi_id. nama_pengakuisisi TETAP
 * disimpan sebagai salinan nama (disalin otomatis pas pengakuisisi_id
 * dipilih) -- baris lama sebelum master data ini ada cuma punya
 * nama_pengakuisisi tanpa pengakuisisi_id (nullable), jadi tampilan/export
 * selalu baca nama_pengakuisisi, gak perlu join ke pengakuisisi_id.
 * Bonus berjenjang 3%/2%/1% dari transaksi bulan ke-1/2/3 mitra SEJAK
 * tanggal_akuisisi (order sebelum itu gak dihitung), masing-masing bulan
 * transaksi diakumulasi jadi satu "transaksi" (bukan per-order). Transaksi
 * ke-2 & ke-3 cuma dapat bonus kalau terjadi dalam 6 bulan sejak tanggal
 * transaksi pertama (bukan sejak tanggal_akuisisi).
 */
#[Fillable(['mitra_id', 'pengakuisisi_id', 'nama_pengakuisisi', 'tanggal_akuisisi', 'created_by'])]
class AkuisisiReferral extends Model
{
    protected $casts = [
        'tanggal_akuisisi' => 'date',
    ];

    private const PERSENTASE = [1 => 3.0, 2 => 2.0, 3 => 1.0];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function pengakuisisi()
    {
        return $this->belongsTo(Pengakuisisi::class);
    }

    public function tahaps()
    {
        return $this->hasMany(AkuisisiReferralTahap::class);
    }

    /**
     * Hitung tahap 1/2/3 LIVE dari data orders (gak disimpan) + gabungkan
     * status pembayaran yang tersimpan (kalau ada). Return max 3 baris,
     * bisa kurang kalau mitra belum transaksi 3 bulan sejak akuisisi.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function hitungTahap(): \Illuminate\Support\Collection
    {
        $rows = DB::table('orders')
            ->where('mitra_id', $this->mitra_id)
            ->whereDate('tanggal_order', '>=', $this->tanggal_akuisisi)
            ->selectRaw('DATE_FORMAT(tanggal_order, "%Y-%m") as bulan_key, MIN(tanggal_order) as tanggal_mulai, SUM(total_transaksi) as nominal')
            ->groupBy('bulan_key')
            ->orderBy('bulan_key')
            ->limit(3)
            ->get();

        $tahapTersimpan = $this->tahaps->keyBy('tahap');
        $batasWindow = null;

        return $rows->values()->map(function ($row, $i) use ($tahapTersimpan, &$batasWindow) {
            $tahap = $i + 1;
            $tanggalMulai = Carbon::parse($row->tanggal_mulai);

            if ($tahap === 1) {
                $batasWindow = $tanggalMulai->copy()->addMonths(6)->subDay();
            }

            $dalamWindow = $tahap === 1 || $tanggalMulai->lte($batasWindow);
            $persentase = self::PERSENTASE[$tahap];
            $nominal = (float) $row->nominal;
            $nominalBonus = $dalamWindow ? round($nominal * $persentase / 100, 2) : 0.0;

            $tersimpan = $tahapTersimpan->get($tahap);

            return (object) [
                'tahap' => $tahap,
                'tanggal_mulai' => $tanggalMulai,
                'bulan_label' => $tanggalMulai->translatedFormat('F Y'),
                'nominal' => $nominal,
                'persentase' => $persentase,
                'nominal_bonus' => $nominalBonus,
                'dalam_window' => $dalamWindow,
                'batas_window' => $batasWindow,
                'status' => $dalamWindow ? ($tersimpan->status ?? 'belum_bayar') : 'di_luar_window',
                'konfirmasi_tanpa_retur' => (bool) ($tersimpan->konfirmasi_tanpa_retur ?? false),
                'catatan' => $tersimpan->catatan ?? null,
                'tahap_id' => $tersimpan->id ?? null,
            ];
        });
    }

    /** Total bonus yang BERHAK diterima (semua tahap dalam window, terlepas status bayar). */
    public function totalBonusBerhak(): float
    {
        return $this->hitungTahap()->where('dalam_window', true)->sum('nominal_bonus');
    }

    /** Total bonus yang SUDAH dibayarkan. */
    public function totalBonusDibayar(): float
    {
        return $this->hitungTahap()->where('status', 'sudah_bayar')->sum('nominal_bonus');
    }
}
