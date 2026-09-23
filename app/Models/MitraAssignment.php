<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'bulan', 'tahun', 'status_bulan', 'status', 'created_by',
])]
class MitraAssignment extends Model
{
    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sesis()
    {
        // Sengaja TANPA orderBy default: sesiAktif() perlu urutan DESC,
        // sedangkan tampilan riwayat perlu ASC — masing-masing pemanggil
        // yang nentuin urutannya sendiri biar tidak numpuk dua ORDER BY
        // yang saling menang berebut (ASC bawaan relasi akan selalu
        // dominan atas orderByDesc() yang ditambahkan belakangan).
        return $this->hasMany(MitraAssignmentSesi::class);
    }

    /**
     * Sesi yang lagi berjalan / terakhir dibuat — selalu urutan tertinggi.
     * Kalau assignment sudah 'selesai', ini sesi terakhir yang statusnya
     * juga 'selesai' (tidak ada sesi baru dibuat lagi setelah user pilih Done).
     */
    public function sesiAktif(): ?MitraAssignmentSesi
    {
        return $this->relationLoaded('sesis')
            ? $this->sesis->sortByDesc('urutan')->first()
            : $this->sesis()->orderByDesc('urutan')->first();
    }

    public function periodeLabel(): string
    {
        return \Carbon\Carbon::create($this->tahun, $this->bulan)->translatedFormat('F Y');
    }
}
