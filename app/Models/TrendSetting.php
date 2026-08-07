<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'ini_mulai', 'ini_selesai', 'lalu_mulai', 'lalu_selesai', 'updated_by'])]
class TrendSetting extends Model
{
    protected function casts(): array
    {
        return [
            'ini_mulai' => 'date',
            'ini_selesai' => 'date',
            'lalu_mulai' => 'date',
            'lalu_selesai' => 'date',
        ];
    }

    /**
     * There's only ever one active comparison shown on the Dashboard.
     */
    public static function active(): ?self
    {
        return static::latest()->first();
    }
}
