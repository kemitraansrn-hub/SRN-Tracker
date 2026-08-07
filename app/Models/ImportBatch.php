<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'jenis', 'tanggal_data', 'bulan', 'tahun', 'nama_file',
    'uploaded_by', 'jumlah_baris', 'status', 'catatan',
])]
class ImportBatch extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_data' => 'date',
        ];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function targetBulanan()
    {
        return $this->hasMany(TargetBulanan::class);
    }
}
