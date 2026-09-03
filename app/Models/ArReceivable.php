<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['order_id', 'jumlah_ar', 'tanggal_input', 'jatuh_tempo_hari', 'tanggal_jatuh_tempo', 'created_by'])]
class ArReceivable extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_input' => 'date',
            'tanggal_jatuh_tempo' => 'date',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(ArPayment::class)->orderBy('cicilan_ke');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalDibayar(): float
    {
        return (float) $this->payments->sum('jumlah_bayar');
    }

    public function sisa(): float
    {
        return max((float) $this->jumlah_ar - $this->totalDibayar(), 0);
    }

    public function isLunas(): bool
    {
        return $this->sisa() <= 0;
    }

    public function isJatuhTempo(): bool
    {
        return ! $this->isLunas() && now()->startOfDay()->gt($this->tanggal_jatuh_tempo->copy()->startOfDay());
    }

    /**
     * Berapa hari sudah lewat dari tanggal jatuh tempo. Null kalau belum
     * jatuh tempo (belum ada yang perlu di-age).
     */
    public function hariTerlambat(): ?int
    {
        if (! $this->isJatuhTempo()) {
            return null;
        }

        return $this->tanggal_jatuh_tempo->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Bucket umur aging (khusus AR yang sudah jatuh tempo) — 14/30 hari
     * jadi termin yang dipakai di sini, jadi bucket-nya dirapatkan
     * mengikuti skala itu, bukan bucket umum 30/60/90 hari.
     */
    public function agingBucket(): ?string
    {
        $hari = $this->hariTerlambat();

        if ($hari === null) {
            return null;
        }

        return match (true) {
            $hari <= 14 => '1-14',
            $hari <= 30 => '15-30',
            $hari <= 60 => '31-60',
            default => '61+',
        };
    }
}
