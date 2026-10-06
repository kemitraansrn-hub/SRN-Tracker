<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mitra_id', 'platform', 'detail', 'isu_kendala', 'created_by'])]
class SetUpLmsIssue extends Model
{
    public const DETAIL_OPTIONS = ['Device', 'Tidak Respon', 'Waktu', 'Dll'];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
