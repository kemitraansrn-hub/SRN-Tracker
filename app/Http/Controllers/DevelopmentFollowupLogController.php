<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentFollowupLog;
use App\Models\Mitra;
use App\Models\User;
use App\Services\NotificationCenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Follow Up Development (Growth Specialist) — beda dari Follow-up Log
 * Sales (FollowupLogController/followup_logs): mitra-nya dari roster
 * Weekly Plan (WeeklyPlanController::rosterMitraIds(), all-time), gak ada
 * Status Belanja/Nominal Belanja, dan Status Follow-up BUKAN dropdown —
 * dihitung otomatis per mitra (FU 1 -> FU 2 -> Red Zone, lihat
 * DevelopmentFollowupLog::statusUntukFollowupBaru()).
 *
 * Scoping mitra pakai pola SetUpLmsController::scopedMitra() (cuma dibatasi
 * KAE kalau role 'kae', role growth_specialist/admin lihat semua) — BUKAN
 * canViewAll(), karena role growth_specialist gak masuk canViewAll() sama
 * sekali (lihat catatan di GrowthSpecialistController).
 */
class DevelopmentFollowupLogController extends Controller
{
    public const ALASAN_KENDALA = FollowupLogController::ALASAN_KENDALA;

    public function index(Request $request): View
    {
        $user = $request->user();

        NotificationCenter::dismiss($user, 'development_followup_redzone_resolved');

        $query = $this->filteredQuery($request)->latest('tanggal_fu');

        return view('development-followup.index', [
            'logs' => $query->paginate(20)->withQueryString(),
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

    public function create(Request $request): View
    {
        return view('development-followup.form', [
            'mitraOptions' => $this->scopedMitraBisaDifollowup($request->user())->orderBy('nama')->get(['id', 'nama', 'kode_mitra']),
            'selectedMitraId' => $request->integer('mitra_id') ?: null,
            'alasanOptions' => self::ALASAN_KENDALA,
        ]);
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

        return redirect()->route('growth-specialist.followup.create')->with('status', 'Follow-up berhasil dicatat.');
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
            'mitra_id.required' => 'Pilih mitra dari daftar dulu (ketik nama lalu klik salah satu saran yang muncul).',
            'mitra_id.exists' => 'Mitra yang dipilih tidak valid, coba pilih ulang dari daftar.',
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
     * scopedMitra() TAPI ngecualikan mitra yang masih Red Zone (belum
     * belanja lagi sejak follow-up ke-3 -- lihat
     * DevelopmentFollowupLog::mitraIdsSedangRedZone()) — dipakai buat
     * create()/store(): mitra Red Zone sengaja gak muncul lagi di
     * pencarian follow-up BARU sampai dia belanja lagi, 2026-11-27.
     */
    private function scopedMitraBisaDifollowup(User $user)
    {
        return $this->scopedMitra($user)->whereNotIn('id', DevelopmentFollowupLog::mitraIdsSedangRedZone());
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
