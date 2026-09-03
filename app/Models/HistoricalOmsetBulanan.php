<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tahun', 'bulan', 'omset', 'created_by'])]
class HistoricalOmsetBulanan extends Model
{
    protected $table = 'historical_omset_bulanan';
}
