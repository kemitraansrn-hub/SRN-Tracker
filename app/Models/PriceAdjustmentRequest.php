<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'toko', 'marketplace', 'link_toko', 'tanggal_mulai', 'tanggal_selesai',
    'status_approval', 'diajukan_oleh', 'disetujui_oleh', 'catatan',
])]
class PriceAdjustmentRequest extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'dilihat_compliance_at' => 'datetime',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function pengaju()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju()
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * True kalau mitra ini punya izin price adjustment yang Approved dan
     * tanggal-nya mencakup $tanggal — dipakai buat blokir input Tracking CP
     * pas penurunan harganya memang sah/diizinkan.
     */
    public static function adaIzinAktif(int $mitraId, \DateTimeInterface|string $tanggal): bool
    {
        return static::where('mitra_id', $mitraId)
            ->where('status_approval', 'Approved')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->exists();
    }
}
