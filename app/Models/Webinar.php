<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nama_webinar', 'tanggal_webinar', 'jumlah_peserta', 'bukti_zoom_url', 'created_by'])]
class Webinar extends Model
{
    public const NAMA_OPTIONS = ['Webinar Pareto', 'Webinar Reguler', 'Webinar Dr. Shindy'];

    protected function casts(): array
    {
        return [
            'tanggal_webinar' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
