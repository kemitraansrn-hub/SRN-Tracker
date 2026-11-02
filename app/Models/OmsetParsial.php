<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Angka manual "omset yang belum masuk database karena masih parsial" —
 * cuma dipakai buat tampilan tambahan di card Dashboard "MTD vs Bulan
 * Lalu" (total real + parsial), TIDAK ngaruh ke perhitungan omset di
 * menu lain mana pun. Di-lock PER BULAN (unique bulan+tahun) -- ganti
 * bulan otomatis mulai dari 0 (belum ada baris buat bulan baru), tapi
 * baris bulan lama tetap ada buat histori, gak ketimpa/ikut kehapus.
 * Admin update in-place untuk bulan yang SEDANG dilihat (bukan insert
 * baris baru tiap edit dalam bulan yang sama) supaya "dihapus" beneran
 * menghapus baris bulan itu, bukan nyisain histori ganda.
 */
#[Fillable(['bulan', 'tahun', 'jumlah', 'keterangan', 'updated_by'])]
class OmsetParsial extends Model
{
    public static function forPeriode(int $bulan, int $tahun): ?self
    {
        return static::query()->where('bulan', $bulan)->where('tahun', $tahun)->first();
    }
}
