<?php

namespace App\Http\Controllers;

use App\Models\OneOnOne;
use App\Models\OneOnOneSesi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "1 on 1": follow-up Zoom coaching untuk mitra yang Status Belanja-nya
 * Kurang Belanja/Belum Belanja/Over RO di Komit Tracker. Metodenya sama
 * persis dengan menu Assignment (dipicu dari Data Development) — lihat
 * AssignmentController — tapi ini set data terpisah, satu mitra bisa
 * punya Assignment DAN 1 on 1 berbarengan tanpa saling ganggu, karena
 * sumber & tujuan follow-up-nya beda (Pareto/RTP Kurang-Warning vs
 * Komit pasca-LMS).
 */
class OneOnOneController extends Controller
{
    public function index(Request $request): View
    {
        $items = $this->filtered($request)->with(['mitra', 'sesis'])
            ->get()
            ->sortBy(fn (OneOnOne $a) => [$a->status === 'selesai' ? 1 : 0, $a->sesiAktif()?->jadwal_zoom])
            ->values();

        $statusBelanjaOptions = OneOnOne::whereNotNull('status_belanja')
            ->distinct()->orderBy('status_belanja')->pluck('status_belanja');

        return view('one-on-one.index', [
            'items' => $items,
            'statusBelanjaOptions' => $statusBelanjaOptions,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $items = $this->filtered($request)->with(['mitra', 'sesis'])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('1 on 1');

        $headers = ['Kode Mitra', 'Nama Mitra', 'Status Belanja', 'Periode', 'Sesi', 'Jadwal Zoom', 'Status Sesi', 'Problem', 'Solusi', 'Action Plan', 'Status 1 on 1'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $r = 2;
        foreach ($items as $item) {
            foreach ($item->sesis->sortBy('urutan') as $sesi) {
                $sheet->fromArray([
                    $item->mitra->kode_mitra ?? '—',
                    $item->mitra->nama ?? '—',
                    $item->status_belanja,
                    $item->periodeLabel(),
                    $sesi->label(),
                    $sesi->jadwal_zoom?->format('d/m/Y H:i'),
                    $sesi->status === 'selesai' ? 'Selesai' : 'Terjadwal',
                    $sesi->problem,
                    $sesi->solusi,
                    $sesi->action_plan,
                    $item->status === 'selesai' ? 'Selesai' : 'Terjadwal',
                ], null, 'A'.$r, true);
                $r++;
            }
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, '1 on 1 '.now()->format('d-m-Y').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Query dasar + semua filter yang sama dipakai index() dan download(). */
    private function filtered(Request $request): Builder
    {
        return OneOnOne::query()
            ->when($request->filled('bulan'), fn ($q) => $q->where('bulan', (int) $request->input('bulan')))
            ->when($request->filled('tahun'), fn ($q) => $q->where('tahun', (int) $request->input('tahun')))
            ->when($request->filled('status_belanja'), fn ($q) => $q->where('status_belanja', $request->input('status_belanja')))
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
            'status_belanja' => ['required', 'string', 'max:50'],
            'jadwal_zoom' => ['required', 'date'],
        ]);

        $sudahAda = OneOnOne::where('mitra_id', $data['mitra_id'])
            ->where('bulan', $data['bulan'])->where('tahun', $data['tahun'])
            ->exists();

        if ($sudahAda) {
            return redirect()->route('growth-specialist.komit-tracker', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
                ->with('status', 'Mitra ini sudah punya 1 on 1 untuk periode ini.');
        }

        DB::transaction(function () use ($data, $request) {
            $item = OneOnOne::create([
                'mitra_id' => $data['mitra_id'],
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'status_belanja' => $data['status_belanja'],
                'status' => 'terjadwal',
                'created_by' => $request->user()->id,
            ]);

            OneOnOneSesi::create([
                'one_on_one_id' => $item->id,
                'urutan' => 1,
                'jadwal_zoom' => $data['jadwal_zoom'],
                'status' => 'terjadwal',
            ]);
        });

        return redirect()->route('growth-specialist.komit-tracker', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
            ->with('status', '1 on 1 berhasil dibuat dan Zoom sudah dijadwalkan.');
    }

    public function reschedule(Request $request, OneOnOne $oneOnOne): RedirectResponse
    {
        $data = $request->validate(['jadwal_zoom' => ['required', 'date']]);

        $sesi = $oneOnOne->sesiAktif();

        if (! $sesi || $sesi->status !== 'terjadwal') {
            return redirect()->route('one-on-one.index')->with('status', '1 on 1 ini sudah selesai, tidak bisa dijadwalkan ulang.');
        }

        $sesi->update(['jadwal_zoom' => $data['jadwal_zoom']]);

        return redirect()->route('one-on-one.index')->with('status', 'Jadwal Zoom '.$sesi->label().' berhasil diupdate.');
    }

    public function complete(Request $request, OneOnOne $oneOnOne): RedirectResponse
    {
        $data = $request->validate([
            'problem' => ['required', 'string'],
            'solusi' => ['required', 'string'],
            'action_plan' => ['required', 'string'],
            'next_action' => ['required', 'in:lanjut,done'],
            'next_jadwal' => ['required_if:next_action,lanjut', 'nullable', 'date'],
        ]);

        $sesi = $oneOnOne->sesiAktif();

        if (! $sesi || $sesi->status !== 'terjadwal') {
            return redirect()->route('one-on-one.index')->with('status', '1 on 1 ini sudah selesai.');
        }

        DB::transaction(function () use ($data, $sesi, $oneOnOne, $request) {
            $sesi->update([
                'problem' => $data['problem'],
                'solusi' => $data['solusi'],
                'action_plan' => $data['action_plan'],
                'status' => 'selesai',
                'filled_by' => $request->user()->id,
                'filled_at' => now(),
            ]);

            if ($data['next_action'] === 'lanjut') {
                OneOnOneSesi::create([
                    'one_on_one_id' => $oneOnOne->id,
                    'urutan' => $sesi->urutan + 1,
                    'jadwal_zoom' => $data['next_jadwal'],
                    'status' => 'terjadwal',
                ]);
            } else {
                $oneOnOne->update(['status' => 'selesai']);
            }
        });

        return redirect()->route('one-on-one.index')->with('status', 'Hasil '.$sesi->label().' tersimpan.');
    }

    public function destroy(OneOnOne $oneOnOne): RedirectResponse
    {
        $nama = $oneOnOne->mitra->nama ?? 'mitra ini';
        $oneOnOne->delete(); // cascade hapus semua sesis (FK cascadeOnDelete)

        return redirect()->route('one-on-one.index')->with('status', '1 on 1 '.$nama.' dihapus. Mitra ini bisa dijadwalkan ulang lagi dari Komit Tracker.');
    }
}
