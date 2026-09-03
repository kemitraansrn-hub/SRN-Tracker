<?php

namespace App\Http\Controllers;

use App\Models\FollowupLog;
use App\Models\Mitra;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FollowupLogController extends Controller
{
    public const ALASAN_KENDALA = [
        'Cashflow / modal belum siap',
        'Penjualan lambat / belum closing customer',
        'Beli via A2A / agen lain',
        'Menunggu respon mitra / tidak dibalas',
        'Iklan belum jalan / kendala iklan',
        'Ragu coba produk baru (Reglow/Amura)',
        'Menunda — janji belanja minggu depan',
        'Tidak bisa dihubungi',
        'Fokus ke channel/brand lain',
        'Komplain produk / kualitas',
        'Harga / kalah saing kompetitor',
        'Kendala operasional / libur',
        'Sedang negosiasi target/MOU (bukan kendala)',
        'Atur jadwal order — libur operasional pusat',
        'Fokus pelunasan piutang / DP',
        'Konfirmasi barang diterima (bukan kendala)',
        'Diarahkan ikut program development',
        'Mitra off / berhenti kemitraan',
        'Stok mitra masih ada / belum habis',
        'Lainnya (tulis di Catatan)',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = FollowupLog::with(['mitra', 'kae'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_user_id', $user->id))
            ->when($request->filled('mitra_id'), fn ($q) => $q->where('mitra_id', $request->input('mitra_id')))
            ->latest('tanggal_fu');

        return view('followup.index', [
            'logs' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $mitraOptions = Mitra::where('status', 'aktif')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get();

        return view('followup.form', [
            'mitraOptions' => $mitraOptions,
            'selectedMitraId' => $request->integer('mitra_id') ?: null,
            'alasanOptions' => self::ALASAN_KENDALA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $mitra = Mitra::findOrFail($data['mitra_id']);

        if (! $user->isAdmin() && $mitra->kae_code !== $user->kae_code) {
            abort(403, 'Anda tidak punya akses ke mitra ini.');
        }

        $tanggal = Carbon::parse($data['tanggal_fu']);
        $totalMenit = $this->hitungTotalMenit($data);

        FollowupLog::create([
            ...$data,
            'kae_user_id' => $user->id,
            'minggu' => \App\Models\WeekPeriod::resolveWeek($tanggal),
            'total_menit' => $totalMenit,
        ]);

        return redirect()->route('followup.create')->with('status', 'Follow-up berhasil dicatat.');
    }

    public function edit(Request $request, FollowupLog $followupLog): View
    {
        $this->authorizeFollowup($request, $followupLog);

        $user = $request->user();
        $mitraOptions = Mitra::where('status', 'aktif')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get();

        return view('followup.form', [
            'log' => $followupLog,
            'mitraOptions' => $mitraOptions,
            'selectedMitraId' => $followupLog->mitra_id,
            'alasanOptions' => self::ALASAN_KENDALA,
        ]);
    }

    public function update(Request $request, FollowupLog $followupLog): RedirectResponse
    {
        $this->authorizeFollowup($request, $followupLog);

        $data = $this->validated($request);
        $mitra = Mitra::findOrFail($data['mitra_id']);

        if (! $request->user()->isAdmin() && $mitra->kae_code !== $request->user()->kae_code) {
            abort(403, 'Anda tidak punya akses ke mitra ini.');
        }

        $tanggal = Carbon::parse($data['tanggal_fu']);

        $followupLog->update([
            ...$data,
            'minggu' => \App\Models\WeekPeriod::resolveWeek($tanggal),
            'total_menit' => $this->hitungTotalMenit($data),
        ]);

        return redirect()->route('followup.index')->with('status', 'Follow-up berhasil diperbarui.');
    }

    public function destroy(Request $request, FollowupLog $followupLog): RedirectResponse
    {
        $this->authorizeFollowup($request, $followupLog);

        $followupLog->delete();

        return redirect()->route('followup.index')->with('status', 'Follow-up berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'tanggal_fu' => ['required', 'date'],
            'status_followup' => ['required', 'in:Terhubung,Tidak ada respon'],
            'status_belanja' => ['required', 'in:Belanja Penuh,Belanja Sebagian,Belum Belanja'],
            'nominal_belanja' => ['nullable', 'numeric', 'min:0'],
            'alasan_kendala' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai'],
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

    private function authorizeFollowup(Request $request, FollowupLog $followupLog): void
    {
        $user = $request->user();

        if (! $user->isAdmin() && $followupLog->kae_user_id !== $user->id) {
            abort(403, 'Anda tidak punya akses ke catatan follow-up ini.');
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $logs = FollowupLog::with(['mitra', 'kae'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_user_id', $user->id))
            ->when($request->filled('mitra_id'), fn ($q) => $q->where('mitra_id', $request->input('mitra_id')))
            ->orderBy('tanggal_fu')
            ->get();

        $headers = ['Tanggal', 'Minggu', 'KAE', 'Nama Mitra', 'Alasan / Kendala', 'Catatan'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Follow-up Log');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBE5EF');
        foreach ([14, 10, 14, 24, 28, 40] as $col => $width) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($width);
        }

        $row = 2;
        foreach ($logs as $log) {
            $sheet->fromArray([
                $log->tanggal_fu->format('d/m/Y'),
                $log->minggu,
                $log->kae->name ?? '—',
                $log->mitra->nama ?? '—',
                $log->alasan_kendala,
                $log->catatan,
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'followup_log_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
