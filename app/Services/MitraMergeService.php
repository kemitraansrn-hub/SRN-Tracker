<?php

namespace App\Services;

use App\Models\Mitra;
use Illuminate\Support\Facades\DB;

/**
 * Gabung 2 baris Mitra yang sebenarnya orang/toko yang sama (biasanya
 * kejadian karena nama gak persis cocok pas upload Target Bulanan/Special
 * Deal, jadi sistem bikin baris Mitra baru alih-alih mencocokkan ke yang
 * udah ada). Semua data transaksi/tracking milik $source dipindah ke
 * $target, $target TIDAK berubah (nama/kode_mitra/dll-nya tetap yang asli),
 * $source dihapus di akhir.
 *
 * 21 tabel terhubung ke mitra_id (lihat information_schema.REFERENTIAL_
 * CONSTRAINTS — satu-satunya sumber yang akurat, jangan percaya daftar ini
 * dari ingatan kalau skema berubah lagi). Dipecah 2 kelompok:
 * - SIMPLE_TABLES: gak ada UNIQUE yang melibatkan mitra_id, aman di-UPDATE
 *   langsung.
 * - CONFLICT_AWARE_TABLES: ada UNIQUE(mitra_id, kolom lain...) — kalau
 *   $target udah punya baris dengan kombinasi kolom yang sama, baris milik
 *   $source utuk kombinasi itu DIBUANG (bukan dipindah) biar gak tabrakan;
 *   $target dianggap sumber kebenaran. Baris yang dibuang HARUS di-DELETE
 *   eksplisit di sini (bukan dibiarkan ke-cascade), soalnya beberapa tabel
 *   (target_bulanan) pakai DELETE_RULE NO ACTION — kalau dibiarkan nyangkut
 *   ke $source, $source->delete() di akhir bakal gagal kena FK constraint.
 */
class MitraMergeService
{
    private const SIMPLE_TABLES = [
        'buyback_requests', 'cp_cases', 'followup_logs', 'forecast_ros',
        'mitra_assignments', 'mitra_snapshots', 'one_on_ones', 'orders',
        'poin_redemptions', 'price_adjustment_requests', 'sales_drafts',
        'set_up_lms_issues', 'special_deals',
    ];

    /** @var array<string, string[]> tabel => kolom lain (selain mitra_id) yang ikut UNIQUE */
    private const CONFLICT_AWARE_TABLES = [
        'lms_enrollments' => ['platform'],
        'lms_step_completions' => ['lms_step_id'],
        'mitra_profil_growth' => [],
        'new_mitra_flags' => ['bulan', 'tahun'],
        'set_up_lms_notes' => ['platform'],
        'stand_in_line_notes' => ['bulan', 'tahun'],
        'target_bulanan' => ['bulan', 'tahun'],
        'tracking_performances' => ['tanggal_mulai', 'tanggal_selesai'],
    ];

    /**
     * @return array<string, array{dipindah: int, dilewati: int}> laporan per tabel (cuma tabel yang kesentuh)
     */
    public function merge(Mitra $source, Mitra $target): array
    {
        abort_if($source->id === $target->id, 422, 'Mitra sumber dan tujuan gak boleh sama.');

        $report = [];

        DB::transaction(function () use ($source, $target, &$report) {
            foreach (self::SIMPLE_TABLES as $table) {
                $moved = DB::table($table)->where('mitra_id', $source->id)->update(['mitra_id' => $target->id]);
                if ($moved > 0) {
                    $report[$table] = ['dipindah' => $moved, 'dilewati' => 0];
                }
            }

            foreach (self::CONFLICT_AWARE_TABLES as $table => $keyCols) {
                $sourceRows = DB::table($table)->where('mitra_id', $source->id)->get();
                $moved = 0;
                $skipped = 0;

                foreach ($sourceRows as $row) {
                    $conflict = DB::table($table)->where('mitra_id', $target->id);
                    foreach ($keyCols as $col) {
                        $conflict->where($col, $row->{$col});
                    }

                    if ($conflict->exists()) {
                        // $target udah punya data buat kombinasi ini — punya $target
                        // menang, baris $source ini dibuang eksplisit (bukan
                        // dibiarkan ke-cascade, lihat catatan class di atas).
                        DB::table($table)->where('id', $row->id)->delete();
                        $skipped++;

                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update(['mitra_id' => $target->id]);
                    $moved++;
                }

                if ($moved > 0 || $skipped > 0) {
                    $report[$table] = ['dipindah' => $moved, 'dilewati' => $skipped];
                }
            }

            $source->delete();
        });

        return $report;
    }
}
