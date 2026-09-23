<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_assignment_id', 'urutan', 'jadwal_zoom', 'status',
    'problem', 'solusi', 'action_plan', 'filled_by', 'filled_at',
])]
class MitraAssignmentSesi extends Model
{
    protected $casts = [
        'jadwal_zoom' => 'datetime',
        'filled_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(MitraAssignment::class, 'mitra_assignment_id');
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
