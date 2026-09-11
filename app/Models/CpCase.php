<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kode', 'tanggal_temuan', 'mitra_id', 'nama_mitra_manual', 'nama_toko', 'platform',
    'kota_kabupaten_id', 'link_etalase', 'kode_barcode', 'produk', 'harga_sop', 'harga_pelanggaran',
    'status_kasus', 'follow_up_1_tanggal', 'follow_up_1_status', 'follow_up_2_tanggal', 'follow_up_2_status',
    'follow_up_3_tanggal', 'follow_up_3_status', 'bukti_temuan', 'bukti_case_close', 'tanggal_case_close',
    'approval_takedown', 'status_takedown', 'banding', 'created_by',
])]
class CpCase extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_temuan' => 'date',
            'follow_up_1_tanggal' => 'date',
            'follow_up_1_status' => 'boolean',
            'follow_up_2_tanggal' => 'date',
            'follow_up_2_status' => 'boolean',
            'follow_up_3_tanggal' => 'date',
            'follow_up_3_status' => 'boolean',
            'tanggal_case_close' => 'date',
            'harga_sop' => 'decimal:2',
            'harga_pelanggaran' => 'decimal:2',
            'approval_takedown' => 'boolean',
            'banding' => 'boolean',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function kotaKabupaten()
    {
        return $this->belongsTo(KotaKabupaten::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function takedownBanding()
    {
        return $this->hasOne(CpTakedownBanding::class);
    }

    public function namaMitraTampil(): string
    {
        return $this->mitra->nama ?? $this->nama_mitra_manual ?? '—';
    }

    public function selisihHarga(): float
    {
        return (float) $this->harga_sop - (float) $this->harga_pelanggaran;
    }

    public function persentaseSelisih(): ?float
    {
        if ((float) $this->harga_sop <= 0) {
            return null;
        }

        return round($this->selisihHarga() / (float) $this->harga_sop * 100, 2);
    }
}
