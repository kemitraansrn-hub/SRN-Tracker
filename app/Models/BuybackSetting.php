<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single admin-controlled depreciation rate used by every Pengajuan Buy
 * Back (locked to 5% unless admin changes it). Only ever one active row —
 * same "latest wins" pattern as TrendSetting.
 */
#[Fillable(['tingkat_penyusutan', 'updated_by'])]
class BuybackSetting extends Model
{
    private const DEFAULT_RATE = 5.0;

    public static function currentRate(): float
    {
        $rate = static::latest()->value('tingkat_penyusutan');

        return $rate !== null ? (float) $rate : self::DEFAULT_RATE;
    }
}
