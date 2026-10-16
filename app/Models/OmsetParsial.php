<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Angka manual "omset yang belum masuk database karena masih parsial" —
 * cuma dipakai buat tampilan tambahan di card Dashboard "MTD vs Bulan
 * Lalu" (total real + parsial), TIDAK ngaruh ke perhitungan omset di
 * menu lain mana pun. Satu baris aktif aja (latest wins, sama pola kayak
 * BuybackSetting) — admin update in-place (bukan insert baris baru tiap
 * edit) supaya "dihapus" beneran menghapus satu-satunya baris, bukan
 * nyisain histori baris lama yang bikin bingung.
 */
#[Fillable(['jumlah', 'keterangan', 'updated_by'])]
class OmsetParsial extends Model
{
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
