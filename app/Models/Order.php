<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'import_batch_id', 'no_order', 'no_order_perpack', 'tanggal_order', 'tanggal_konfirmasi',
    'mitra_id', 'total_transaksi', 'diskon', 'diskon_claim', 'diskon_return',
    'biaya_pendaftaran', 'ongkir', 'biaya_penanganan', 'total_transfer',
    'status_pembayaran', 'status', 'kae_code', 'id_channel',
    'is_edited', 'edited_by', 'edited_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_order' => 'date',
            'tanggal_konfirmasi' => 'date',
            'edited_at' => 'datetime',
            'is_edited' => 'boolean',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
