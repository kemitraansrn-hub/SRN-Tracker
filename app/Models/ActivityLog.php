<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'aksi', 'tabel_terkait', 'record_id', 'data_lama', 'data_baru'])]
class ActivityLog extends Model
{
    protected function casts(): array
    {
        return [
            'data_lama' => 'array',
            'data_baru' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
