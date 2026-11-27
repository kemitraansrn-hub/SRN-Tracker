<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentFollowupLog;
use App\Models\Mitra;
use App\Models\SpecialDeal;
use App\Models\User;
use App\Services\NotificationCenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Follow Up Development (Growth Specialist) — beda dari Follow-up Log
 * Sales (FollowupLogController/followup_logs): mitra-nya dari roster
 * Weekly Plan (WeeklyPlanController::rosterMitraIds()), gak ada Status
 * Belanja/Nominal Belanja, dan Status Follow-up BUKAN dropdown — dihitung
 * otomatis per mitra (FU 1 -> FU 2 -> Red Zone, lihat
 * DevelopmentFollowupLog::statusUntukFollowupBaru()).
 *
 * index() (2026-11-27, update) BUKAN log-list kayak Follow-up Log Sales —
 * satu baris per MITRA dari roster Weekly Plan bulan/tahun terpilih
 * (default bulan berjalan), dengan histori follow-up-nya ditampilin
 * berjejer sebagai chip yang bisa diklik (lihat riwayatnya), bukan numpuk
 * jadi baris-baris baru. Gak ada lagi halaman create() terpisah — nyatat
 * follow-up baru lewat modal langsung dari baris mitranya (lihat
 * development-followup/index.blade.php), store() balik ke index()
 * (bukan ke halaman create yang sudah dihapus).
 *
 * Scoping mitra pakai pola SetUpLmsController::scopedMitra() (cuma dibatasi
 * KAE kalau role 'kae', role growth_specialist/admin lihat semua) — BUKAN
 * canViewAll(), karena role growth_specialist gak masuk canViewAll() sama
 * sekali (lihat catatan di GrowthSpecialistController).
 */
class DevelopmentFollowupLogController extends Controller
{
    public const ALASAN_KENDALA = ['Respon', 'Tidak Respon'];

    public function index(Request $request): View
    {
        $user = $request->user();

        NotificationCenter::dismiss($user, 'development_followup_redzone_resolved');

        $bulan = max(1, min(12, (int) $request->input('bulan', now()->month)));
        $tahun = max(2000, min(2100, (int) $request->input('tahun', now()->year)));
        $now = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $q = trim((string) $request->input('q'));
        $statusFu = trim((string) $request->input('status_fu'));

        // Roster = SAMA PERSIS sumbernya kayak menu Weekly Plan (mitra yang
        // punya data upload bulan/tahun ini), bukan semua mitra Data Mitra.
        // whereDoesntHave('profilGrowth') -- cuma tampilin mitra yang BELUM
        // masuk Profiling (2026-11-27, per instruksi user): tujuan menu ini
        // mendorong mitra masuk Profiling, begitu sudah masuk gak perlu
        // di-follow-up lagi lewat sini.
        $mitraIdsPeriode = WeeklyPlanController::rosterMitraIds($bulan, $tahun);

        $mitraList = $this->scopedMitra($user)
            ->whereIn('id', $mitraIdsPeriode)
            ->whereDoesntHave('profilGrowth')
            ->when($q !== '', fn ($qr) => $qr->where('nama', 'like', "%{$q}%"))
            ->when($user->role !== 'kae' && $request->filled('kae_code'), fn ($qr) => $qr->where('kae_code', $request->input('kae_code')))
            ->orderBy('nama')
            ->get();

        $segmenMap = SpecialDeal::segmenByMitraId($now);
        $kaeMap = User::kaeNameMap();

        // Histori follow-up per mitra, urut kronologis -- ditampilin
        // berjejer sebagai chip yang bisa diklik (bukan satu baris per
        // follow-up kayak Follow-up Log Sales).
        $logsByMitra = DevelopmentFollowupLog::whereIn('mitra_id', $mitraList->pluck('id'))
            ->orderBy('tanggal_fu')->orderBy('id')
            ->get()
            ->groupBy('mitra_id');

        $mitraSedangRedZone = DevelopmentFollowupLog::mitraIdsSedangRedZone();

        $rows = $mitraList->map(function (Mitra $m) use ($kaeMap, $segmenMap, $logsByMitra, $mitraSedangRedZone) {
            $logs = $logsByMitra->get($m->id, collect());

            return (object) [
                'mitra' => $m,
                'kae' => $m->kae_code ? ($kaeMap[$m->kae_code] ?? $m->kae_code) : null,
                'segmen' => $segmenMap[$m->id] ?? null,
                'logs' => $logs,
                'statusTerakhir' => $logs->last()->status_followup ?? 'Belum FU',
                'sedangRedZone' => $mitraSedangRedZone->contains($m->id),
            ];
        });

        // Filter by Status FU, diterapkan sesudah $rows dirakit karena
        // status-nya hasil hitungan dari histori log, bukan kolom di
        // tabel mitra, 2026-11-27. "Red Zone" BUKAN label literal yang
        // tersimpan (itu "FU 3", lihat
        // DevelopmentFollowupLog::STATUS_PEMICU_RED_ZONE) -- filter ini
        // cocokkan ke $sedangRedZone (lagi diblokir, belum belanja lagi),
        // bukan ke teks statusTerakhir, biar mitra yang sudah lulus/belanja
        // lagi (gak lagi diblokir) gak ikut kefilter walau log terakhirnya
        // masih "FU 3".
        if ($statusFu === 'Red Zone') {
            $rows = $rows->where('sedangRedZone', true)->values();
        } elseif ($statusFu !== '') {
            $rows = $rows->where('statusTerakhir', $statusFu)->values();
        }

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $rowsPage = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('development-followup.index', [
            'rowsPage' => $rowsPage,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'periodeLabel' => $now->translatedFormat('F Y'),
            'q' => $q,
            'statusFu' => $statusFu,
            'alasanOptions' => self::ALASAN_KENDALA,
            'kaeOptions' => $user->role === 'kae' ? collect() : User::where('role', 'kae')->orderBy('name')->get(),
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $user = $request->user();

        return DevelopmentFollowupLog::with(['mitra', 'user'])
            ->when($user->role === 'kae', fn ($q) => $q->whereHas('mitra', fn ($m) => $m->where('kae_code', $user->kae_code)))
            ->when($request->filled('mitra_id'), fn ($q) => $q->where('mitra_id', $request->input('mitra_id')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('mitra', fn ($m) => $m->where('nama', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('status_followup'), fn ($q) => $q->where('status_followup', $request->input('status_followup')))
            ->when($request->filled('alasan_kendala'), fn ($q) => $q->where('alasan_kendala', $request->input('alasan_kendala')))
            ->when($user->role !== 'kae' && $request->filled('kae_code'), fn ($q) => $q->whereHas('mitra', fn ($m) => $m->where('kae_code', $request->input('kae_code'))))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal_fu', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal_fu', '<=', $request->input('sampai')));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        // scopedMitraBisaDifollowup() (BUKAN scopedMitra() biasa) -- mitra
        // yang masih Red Zone gak boleh di-follow-up lagi sampai belanja,
        // baik dari UI (gak muncul di daftar) maupun kalau ada yang coba
        // kirim mitra_id-nya langsung.
        $mitra = $this->scopedMitraBisaDifollowup($user)->findOrFail($data['mitra_id']);

        $tanggal = Carbon::parse($data['tanggal_fu']);

        DevelopmentFollowupLog::create([
            ...$data,
            'user_id' => $user->id,
            'minggu' => \App\Models\WeekPeriod::resolveWeek($tanggal),
            'status_followup' => DevelopmentFollowupLog::statusUntukFollowupBaru($mitra->id),
            'total_menit' => $this->hitungTotalMenit($data),
        ]);

        // Modal Catat Follow Up dipanggil langsung dari tabel index() --
        // balik ke situ (bukan ke halaman create, yang sudah dihapus
        // 2026-11-27 karena digantikan modal inline).
        return back()->with('status', 'Follow-up untuk '.$mitra->nama.' berhasil dicatat.');
    }

    public function edit(Request $request, DevelopmentFollowupLog $followupLog): View
    {
        return view('development-followup.form', [
            'log' => $followupLog,
            'mitraOptions' => $this->scopedMitra($request->user())->orderBy('nama')->get(['id', 'nama', 'kode_mitra']),
            'selectedMitraId' => $followupLog->mitra_id,
            'alasanOptions' => self::ALASAN_KENDALA,
        ]);
    }

    public function update(Request $request, DevelopmentFollowupLog $followupLog): RedirectResponse
    {
        $data = $this->validated($request);
        $mitra = $this->scopedMitra($request->user())->findOrFail($data['mitra_id']);

        $tanggal = Carbon::parse($data['tanggal_fu']);

        // status_followup SENGAJA gak diubah di sini — itu snapshot urutan
        // follow-up ke-berapa PAS dicatat pertama kali, edit cuma betulin
        // field lain (tanggal/catatan/dst), bukan ngulang hitungan eskalasi.
        $followupLog->update([
            ...$data,
            'minggu' => \App\Models\WeekPeriod::resolveWeek($tanggal),
            'total_menit' => $this->hitungTotalMenit($data),
        ]);

        return redirect()->route('growth-specialist.followup.index')->with('status', 'Follow-up berhasil diperbarui.');
    }

    public function destroy(Request $request, DevelopmentFollowupLog $followupLog): RedirectResponse
    {
        $this->scopedMitra($request->user())->findOrFail($followupLog->mitra_id);

        $followupLog->delete();

        return redirect()->route('growth-specialist.followup.index')->with('status', 'Follow-up berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'tanggal_fu' => ['required', 'date'],
            'alasan_kendala' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai'],
        ], [
            'mitra_id.required' => 'Mitra tidak ditemukan, coba muat ulang halaman lalu buka lagi modal Catat Follow-up-nya.',
            'mitra_id.exists' => 'Mitra tidak valid, coba muat ulang halaman lalu buka lagi modal Catat Follow-up-nya.',
        ]);
    }

    private function hitungTotalMenit(array $data): ?int
    {
        if (empty($data['jam_mulai']) || empty($data['jam_selesai'])) {
            return null;
        }

        return Carbon::createFromFormat('H:i', $data['jam_mulai'])
            ->diffInMinutes(Carbon::createFromFormat('H:i', $data['jam_selesai']));
    }

    /**
     * Mitra dari roster Weekly Plan (all-time) + aktif — discope KAE cuma
     * kalau role 'kae', konsisten sama pola GS controller lain. Dipakai
     * buat edit()/update()/destroy() -- ubah/hapus catatan LAMA tetap
     * boleh walau mitra-nya sekarang lagi Red Zone (gak ada hubungannya
     * sama larangan follow-up BARU di bawah).
     */
    private function scopedMitra(User $user)
    {
        return Mitra::where('status', 'aktif')
            ->whereIn('id', WeeklyPlanController::rosterMitraIds())
            ->when($user->role === 'kae', fn ($q) => $q->where('kae_code', $user->kae_code));
    }

    /**
     * scopedMitra() TAPI ngecualikan: (1) mitra yang masih Red Zone (belum
     * belanja lagi sejak follow-up ke-3 -- lihat
     * DevelopmentFollowupLog::mitraIdsSedangRedZone()), dan (2) mitra yang
     * SUDAH masuk Profiling (sama seperti index(), tujuan follow-up ini
     * mendorong mitra masuk Profiling) — dipakai buat store(): mitra yang
     * sudah masuk salah satu kondisi di atas sengaja gak boleh
     * di-follow-up BARU lagi (tombol "Catat Follow Up"-nya juga gak
     * muncul di index() karena baris mitranya sendiri udah gak
     * ditampilkan), 2026-11-27.
     */
    private function scopedMitraBisaDifollowup(User $user)
    {
        return $this->scopedMitra($user)
            ->whereDoesntHave('profilGrowth')
            ->whereNotIn('id', DevelopmentFollowupLog::mitraIdsSedangRedZone());
    }

    public function export(Request $request): StreamedResponse
    {
        $logs = $this->filteredQuery($request)->orderBy('tanggal_fu')->get();

        $headers = ['Tanggal', 'Minggu', 'Dicatat Oleh', 'Nama Mitra', 'Status FU', 'Alasan / Kendala', 'Catatan'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Follow Up Development');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBE5EF');
        foreach ([14, 10, 18, 24, 12, 28, 40] as $col => $width) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($width);
        }

        $row = 2;
        foreach ($logs as $log) {
            $sheet->fromArray([
                $log->tanggal_fu->format('d/m/Y'),
                $log->minggu,
                $log->user->name ?? '—',
                $log->mitra->nama ?? '—',
                $log->status_followup,
                $log->alasan_kendala,
                $log->catatan,
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'followup_development_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
