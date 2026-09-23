<?php

namespace App\Http\Controllers;

use App\Models\MitraAssignment;
use App\Models\MitraAssignmentSesi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Assignment": follow-up Zoom coaching untuk mitra Pareto/RTP yang Kurang
 * atau Warning di Data Development. Satu assignment dibuat sekali per
 * mitra+bulan snapshot, lalu bisa punya beberapa sesi Zoom berurutan
 * (Sesi 1, 2, 3, ...) — sesi baru dibuat begitu user pilih "Lanjut Sesi
 * berikutnya" setelah mengisi hasil sesi sebelumnya. Sesi dengan urutan
 * tertinggi selalu sesi yang aktif/relevan (lihat MitraAssignment::sesiAktif()).
 */
class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = $this->filtered($request)->with(['mitra', 'sesis'])
            ->get()
            ->sortBy(fn (MitraAssignment $a) => [$a->status === 'selesai' ? 1 : 0, $a->sesiAktif()?->jadwal_zoom])
            ->values();

        $statusBulanOptions = MitraAssignment::whereNotNull('status_bulan')
            ->distinct()->orderBy('status_bulan')->pluck('status_bulan');

        return view('assignment.index', [
            'assignments' => $assignments,
            'statusBulanOptions' => $statusBulanOptions,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $assignments = $this->filtered($request)->with(['mitra', 'sesis'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Assignment');

        $headers = ['Kode Mitra', 'Nama Mitra', 'Status Bulan', 'Periode', 'Sesi', 'Jadwal Zoom', 'Status Sesi', 'Problem', 'Solusi', 'Action Plan', 'Status Assignment'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $r = 2;
        foreach ($assignments as $a) {
            foreach ($a->sesis->sortBy('urutan') as $sesi) {
                $sheet->fromArray([
                    $a->mitra->kode_mitra ?? '—',
                    $a->mitra->nama ?? '—',
                    $a->status_bulan,
                    $a->periodeLabel(),
                    $sesi->label(),
                    $sesi->jadwal_zoom?->format('d/m/Y H:i'),
                    $sesi->status === 'selesai' ? 'Selesai' : 'Terjadwal',
                    $sesi->problem,
                    $sesi->solusi,
                    $sesi->action_plan,
                    $a->status === 'selesai' ? 'Selesai' : 'Terjadwal',
                ], null, 'A'.$r, true);
                $r++;
            }
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'Assignment '.now()->format('d-m-Y').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Query dasar + semua filter yang sama dipakai index() dan download(), biar Excel selalu selaras dengan yang lagi ditampilkan di layar. */
    private function filtered(Request $request): Builder
    {
        return MitraAssignment::query()
            ->when($request->filled('bulan'), fn ($q) => $q->where('bulan', (int) $request->input('bulan')))
            ->when($request->filled('tahun'), fn ($q) => $q->where('tahun', (int) $request->input('tahun')))
            ->when($request->filled('status_bulan'), fn ($q) => $q->where('status_bulan', $request->input('status_bulan')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $needle = $request->input('q');
                $q->whereHas('mitra', fn ($m) => $m->where('nama', 'like', "%{$needle}%")->orWhere('kode_mitra', 'like', "%{$needle}%"));
            });
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer'],
            'status_bulan' => ['required', 'string', 'max:50'],
            'jadwal_zoom' => ['required', 'date'],
        ]);

        $sudahAda = MitraAssignment::where('mitra_id', $data['mitra_id'])
            ->where('bulan', $data['bulan'])->where('tahun', $data['tahun'])
            ->exists();

        if ($sudahAda) {
            return redirect()->route('data-development.index', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
                ->with('status', 'Mitra ini sudah punya Assignment untuk periode ini.');
        }

        DB::transaction(function () use ($data, $request) {
            $assignment = MitraAssignment::create([
                'mitra_id' => $data['mitra_id'],
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'status_bulan' => $data['status_bulan'],
                'status' => 'terjadwal',
                'created_by' => $request->user()->id,
            ]);

            MitraAssignmentSesi::create([
                'mitra_assignment_id' => $assignment->id,
                'urutan' => 1,
                'jadwal_zoom' => $data['jadwal_zoom'],
                'status' => 'terjadwal',
            ]);
        });

        return redirect()->route('data-development.index', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
            ->with('status', 'Assignment berhasil dibuat dan Zoom sudah dijadwalkan.');
    }

    public function reschedule(Request $request, MitraAssignment $mitraAssignment): RedirectResponse
    {
        $data = $request->validate(['jadwal_zoom' => ['required', 'date']]);

        $sesi = $mitraAssignment->sesiAktif();

        if (! $sesi || $sesi->status !== 'terjadwal') {
            return redirect()->route('assignment.index')->with('status', 'Assignment ini sudah selesai, tidak bisa dijadwalkan ulang.');
        }

        $sesi->update(['jadwal_zoom' => $data['jadwal_zoom']]);

        return redirect()->route('assignment.index')->with('status', 'Jadwal Zoom '.$sesi->label().' berhasil diupdate.');
    }

    public function complete(Request $request, MitraAssignment $mitraAssignment): RedirectResponse
    {
        $data = $request->validate([
            'problem' => ['required', 'string'],
            'solusi' => ['required', 'string'],
            'action_plan' => ['required', 'string'],
            'next_action' => ['required', 'in:lanjut,done'],
            'next_jadwal' => ['required_if:next_action,lanjut', 'nullable', 'date'],
        ]);

        $sesi = $mitraAssignment->sesiAktif();

        if (! $sesi || $sesi->status !== 'terjadwal') {
            return redirect()->route('assignment.index')->with('status', 'Assignment ini sudah selesai.');
        }

        DB::transaction(function () use ($data, $sesi, $mitraAssignment, $request) {
            $sesi->update([
                'problem' => $data['problem'],
                'solusi' => $data['solusi'],
                'action_plan' => $data['action_plan'],
                'status' => 'selesai',
                'filled_by' => $request->user()->id,
                'filled_at' => now(),
            ]);

            if ($data['next_action'] === 'lanjut') {
                MitraAssignmentSesi::create([
                    'mitra_assignment_id' => $mitraAssignment->id,
                    'urutan' => $sesi->urutan + 1,
                    'jadwal_zoom' => $data['next_jadwal'],
                    'status' => 'terjadwal',
                ]);
            } else {
                $mitraAssignment->update(['status' => 'selesai']);
            }
        });

        return redirect()->route('assignment.index')->with('status', 'Hasil '.$sesi->label().' tersimpan.');
    }

    public function destroy(MitraAssignment $mitraAssignment): RedirectResponse
    {
        $nama = $mitraAssignment->mitra->nama ?? 'mitra ini';
        $mitraAssignment->delete(); // cascade hapus semua sesis (FK cascadeOnDelete)

        return redirect()->route('assignment.index')->with('status', 'Assignment '.$nama.' dihapus. Mitra ini bisa dijadwalkan ulang lagi dari Data Development.');
    }
}
