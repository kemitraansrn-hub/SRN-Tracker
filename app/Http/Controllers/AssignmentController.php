<?php

namespace App\Http\Controllers;

use App\Models\MitraAssignment;
use App\Models\MitraAssignmentSesi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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
    public function index(): View
    {
        $assignments = MitraAssignment::with(['mitra', 'sesis'])
            ->get()
            ->sortBy(fn (MitraAssignment $a) => [$a->status === 'selesai' ? 1 : 0, $a->sesiAktif()?->jadwal_zoom])
            ->values();

        return view('assignment.index', ['assignments' => $assignments]);
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
}
