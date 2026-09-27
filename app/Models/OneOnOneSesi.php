<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'one_on_one_id', 'urutan', 'jadwal_zoom', 'status',
    'problem', 'solusi', 'action_plan', 'filled_by', 'filled_at',
])]
class OneOnOneSesi extends Model
{
    protected $casts = [
        'jadwal_zoom' => 'datetime',
        'filled_at' => 'datetime',
    ];

    public function oneOnOne()
    {
        return $this->belongsTo(OneOnOne::class);
    }

    public function filler()
    {
        return $this->belongsTo(User::class, 'filled_by');
    }

    public function label(): string
    {
        return 'Sesi '.$this->urutan;
    }
}
