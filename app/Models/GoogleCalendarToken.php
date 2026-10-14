<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'access_token', 'refresh_token', 'expires_at', 'google_email', 'connected_by',
])]
class GoogleCalendarToken extends Model
{
    protected $table = 'google_calendar_tokens';

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function connectedBy()
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /** Satu akun bersama — selalu ambil baris yang paling baru connect. */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
