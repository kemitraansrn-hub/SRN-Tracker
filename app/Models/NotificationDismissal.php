<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'kategori', 'dismissed_at'])]
class NotificationDismissal extends Model
{
    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
