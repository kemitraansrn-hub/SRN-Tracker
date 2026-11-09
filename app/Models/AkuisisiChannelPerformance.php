<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'channel_id', 'bulan', 'tahun', 'lead', 'prospect', 'closing', 'biaya', 'investasi_mitra',
    'created_by', 'updated_by',
])]
class AkuisisiChannelPerformance extends Model
{
    protected function casts(): array
    {
        return [
            'biaya' => 'decimal:2',
            'investasi_mitra' => 'decimal:2',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AkuisisiChannel::class, 'channel_id');
    }
}
