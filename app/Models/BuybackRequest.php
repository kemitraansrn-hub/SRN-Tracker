<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'aov', 'tingkat_penyusutan', 'grand_nilai_beli', 'grand_nilai_penyusutan', 'grand_nilai_buyback',
    'status', 'approved_by', 'approved_at', 'created_by',
    'approved_by_head_id', 'approved_by_head_at', 'approved_by_finance_id', 'approved_by_finance_at',
])]
class BuybackRequest extends Model
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'approved_by_head_at' => 'datetime',
            'approved_by_finance_at' => 'datetime',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function items()
    {
        return $this->hasMany(BuybackRequestItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function headApprover()
    {
        return $this->belongsTo(User::class, 'approved_by_head_id');
    }

    public function financeApprover()
    {
        return $this->belongsTo(User::class, 'approved_by_finance_id');
    }

    public function isHeadApproved(): bool
    {
        return $this->approved_by_head_id !== null;
    }

    public function isFinanceApproved(): bool
    {
        return $this->approved_by_finance_id !== null;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
