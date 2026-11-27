<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

#[Fillable([
    'tanggal_fu', 'minggu', 'mitra_id', 'user_id',
    'status_followup', 'alasan_kendala', 'catatan',
    'jam_mulai', 'jam_selesai', 'total_menit',
])]
class DevelopmentFollowupLog extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_fu' => 'date',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Label "FU 3" dipakai sebagai PEMICU status Red Zone (lihat
     * mitraIdsSedangRedZone()) — sengaja BUKAN disimpan literal "Red Zone"
     * di kolom status_followup, biar gak dobel tampil "Red Zone" + "Red
     * Zone" di tabel (chip histori + label kolom Aksi) — chip histori
     * tetap nampilin "FU 3" apa adanya, status "Red Zone"-nya cuma
     * muncul di kolom Aksi (2026-11-27, koreksi dari user).
     */
    public const STATUS_PEMICU_RED_ZONE = 'FU 3';

    /**
     * Status eskalasi follow-up buat mitra ini SETELAH baris baru ini
     * dicatat — all-time, gak reset bulanan: follow-up ke-1 "FU 1", ke-2
     * "FU 2", ke-3 dan seterusnya "FU 3" (yang otomatis bikin mitra ini
     * masuk status Red Zone, lihat mitraIdsSedangRedZone()). KECUALI
     * mitra ini sudah pernah "lulus" dari Red Zone (belanja lagi
     * sesudahnya) — follow-up SESUDAH titik lulus itu dihitung dari 1
     * lagi (siklus baru), 2026-11-27.
     */
    public static function statusUntukFollowupBaru(int $mitraId): string
    {
        $n = self::hitungFollowupSiklusBerjalan($mitraId) + 1;

        return match (true) {
            $n === 1 => 'FU 1',
            $n === 2 => 'FU 2',
            default => self::STATUS_PEMICU_RED_ZONE,
        };
    }

    /**
     * Jumlah follow-up di siklus eskalasi yang BERJALAN SEKARANG. Default-nya
     * all-time (semua baris), TAPI kalau mitra ini pernah kena Red Zone dan
     * sesudahnya belanja lagi (titik "lulus" terakhir), follow-up SEBELUM
     * titik itu gak dihitung lagi — siklusnya restart dari 0.
     */
    private static function hitungFollowupSiklusBerjalan(int $mitraId): int
    {
        $logs = self::where('mitra_id', $mitraId)->orderBy('tanggal_fu')->orderBy('id')->get(['tanggal_fu', 'status_followup']);

        if ($logs->isEmpty()) {
            return 0;
        }

        $titikLulusTerakhir = null;
        foreach ($logs as $log) {
            if ($log->status_followup === self::STATUS_PEMICU_RED_ZONE && self::sudahBelanjaSetelah($mitraId, $log->tanggal_fu)) {
                $titikLulusTerakhir = $log->tanggal_fu;
            }
        }

        if ($titikLulusTerakhir === null) {
            return $logs->count();
        }

        return $logs->filter(fn ($l) => $l->tanggal_fu->gt($titikLulusTerakhir))->count();
    }

    private static function sudahBelanjaSetelah(int $mitraId, Carbon $tanggal): bool
    {
        return DB::table('orders')
            ->where('mitra_id', $mitraId)
            ->whereDate('tanggal_order', '>', $tanggal)
            ->exists();
    }

    /**
     * Follow-up TERAKHIR per mitra (cuma yang statusnya "FU 3", si pemicu
     * Red Zone) — basis buat mitraIdsSedangRedZone()/partisiRedZoneBelanja()
     * di bawah.
     *
     * @return \Illuminate\Support\Collection<int, self> mitra_id => log terakhir
     */
    private static function redZoneMitraTerakhir(): \Illuminate\Support\Collection
    {
        return self::orderBy('tanggal_fu')->orderBy('id')
            ->get(['mitra_id', 'tanggal_fu', 'status_followup'])
            ->groupBy('mitra_id')
            ->map(fn ($logs) => $logs->last())
            ->filter(fn ($log) => $log->status_followup === self::STATUS_PEMICU_RED_ZONE);
    }

    /**
     * Pisahkan mitra yang follow-up terakhirnya Red Zone jadi 2: yang
     * BELUM belanja lagi (masih Red Zone, dikecualikan dari pencarian
     * mitra di form Catat Follow Up) dan yang SUDAH belanja lagi (lulus,
     * balik ke pencarian + muncul di notifikasi). $latestResolvedAt =
     * tanggal order ter-baru di antara yang "lulus", dipakai notifikasi
     * buat nentuin unread/gak.
     *
     * @return array{belum: \Illuminate\Support\Collection<int,int>, sudah: \Illuminate\Support\Collection<int,int>, latestResolvedAt: ?Carbon}
     */
    public static function partisiRedZoneBelanja(): array
    {
        $redZoneMitra = self::redZoneMitraTerakhir();

        if ($redZoneMitra->isEmpty()) {
            return ['belum' => collect(), 'sudah' => collect(), 'latestResolvedAt' => null];
        }

        $ordersByMitra = DB::table('orders')
            ->whereIn('mitra_id', $redZoneMitra->keys())
            ->get(['mitra_id', 'tanggal_order'])
            ->groupBy('mitra_id');

        $belum = collect();
        $sudah = collect();
        $latestResolvedAt = null;

        foreach ($redZoneMitra as $mitraId => $log) {
            $ordersSetelah = ($ordersByMitra->get($mitraId) ?? collect())
                ->map(fn ($o) => Carbon::parse($o->tanggal_order))
                ->filter(fn ($d) => $d->gt($log->tanggal_fu));

            if ($ordersSetelah->isNotEmpty()) {
                $sudah->push($mitraId);
                $maxDate = $ordersSetelah->max();
                if (! $latestResolvedAt || $maxDate->gt($latestResolvedAt)) {
                    $latestResolvedAt = $maxDate;
                }
            } else {
                $belum->push($mitraId);
            }
        }

        return ['belum' => $belum->values(), 'sudah' => $sudah->values(), 'latestResolvedAt' => $latestResolvedAt];
    }

    /**
     * Mitra yang SEKARANG masih Red Zone (follow-up terakhirnya Red Zone,
     * belum belanja lagi) — dipakai buat ngecualikan dari pencarian mitra
     * di form Catat Follow Up BARU (DevelopmentFollowupLogController).
     */
    public static function mitraIdsSedangRedZone(): \Illuminate\Support\Collection
    {
        return self::partisiRedZoneBelanja()['belum'];
    }
}
