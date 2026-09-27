<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'bulan', 'tahun', 'status_belanja', 'status', 'created_by',
])]
class OneOnOne extends Model
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
        // Sengaja TANPA orderBy default — lihat catatan yang sama di
        // MitraAssignment::sesis(), pola/bug yang sama berlaku di sini.
        return $this->hasMany(OneOnOneSesi::class);
    }

    /** Sesi yang lagi berjalan / terakhir dibuat — selalu urutan tertinggi. */
    public function sesiAktif(): ?OneOnOneSesi
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
