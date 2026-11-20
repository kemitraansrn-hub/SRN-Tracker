<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'import_batch_id', 'no_order', 'no_order_perpack', 'tanggal_order', 'tanggal_konfirmasi',
    'mitra_id', 'total_transaksi', 'qty', 'diskon', 'diskon_claim', 'diskon_return', 'diskon_return_id',
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

    public function arReceivable()
    {
        return $this->hasOne(ArReceivable::class);
    }

    /**
     * Total omset order dengan Tanggal Order = hari ini, dari sumber manapun
     * (sync API maupun upload manual). Dipakai buat card "Omset Hari Ini" di
     * Dashboard & Import Data -- sengaja gak di-cache, biar resetnya otomatis
     * tiap ganti tanggal cuma karena query-nya selalu "hari ini".
     */
    public static function omsetHariIni(): float
    {
        return (float) static::whereDate('tanggal_order', today())->sum('total_transaksi');
    }

    public static function jumlahOrderHariIni(): int
    {
        return static::whereDate('tanggal_order', today())->count();
    }
}
