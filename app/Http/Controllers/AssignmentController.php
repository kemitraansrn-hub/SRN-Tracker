<?php

namespace App\Http\Controllers;

use App\Models\MitraAssignment;
use App\Models\MitraAssignmentSesi;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function __construct(private readonly GoogleCalendarService $calendar) {}

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
            'kaeOptions' => $request->user()->canViewAll() ? User::where('role', 'kae')->orderBy('name')->get() : collect(),
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
        $user = $request->user();

        return MitraAssignment::query()
            // 2026-10-12: sebelumnya gak ada pembatasan KAE sama sekali —
            // dibenerin konsisten sama menu lain.
            ->when(! $user->canViewAll(), fn ($q) => $q->whereHas('mitra', fn ($m) => $m->where('kae_code', $user->kae_code)))
            ->when($user->canViewAll() && $request->filled('kae_code'), fn ($q) => $q->whereHas('mitra', fn ($m) => $m->where('kae_code', $request->input('kae_code'))))
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

        $sesi = DB::transaction(function () use ($data, $request) {
            $assignment = MitraAssignment::create([
                'mitra_id' => $data['mitra_id'],
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'status_bulan' => $data['status_bulan'],
                'status' => 'terjadwal',
                'created_by' => $request->user()->id,
            ]);

            return MitraAssignmentSesi::create([
                'mitra_assignment_id' => $assignment->id,
                'urutan' => 1,
                'jadwal_zoom' => $data['jadwal_zoom'],
                'status' => 'terjadwal',
            ]);
        });

        $calendarWarning = $this->syncSesiToCalendar($sesi->fresh('assignment.mitra'));

        return redirect()->route('data-development.index', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
            ->with('status', 'Assignment berhasil dibuat dan Zoom sudah dijadwalkan.'.$calendarWarning);
    }

    public function reschedule(Request $request, MitraAssignment $mitraAssignment): RedirectResponse
    {
        $data = $request->validate(['jadwal_zoom' => ['required', 'date']]);

        $sesi = $mitraAssignment->sesiAktif();

        if (! $sesi || $sesi->status !== 'terjadwal') {
            return redirect()->route('assignment.index')->with('status', 'Assignment ini sudah selesai, tidak bisa dijadwalkan ulang.');
        }

        $sesi->update(['jadwal_zoom' => $data['jadwal_zoom']]);

        $calendarWarning = $this->syncSesiToCalendar($sesi->fresh('assignment.mitra'));

        return redirect()->route('assignment.index')->with('status', 'Jadwal Zoom '.$sesi->label().' berhasil diupdate.'.$calendarWarning);
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

        $sesiBaru = DB::transaction(function () use ($data, $sesi, $mitraAssignment, $request) {
            $sesi->update([
                'problem' => $data['problem'],
                'solusi' => $data['solusi'],
                'action_plan' => $data['action_plan'],
                'status' => 'selesai',
                'filled_by' => $request->user()->id,
                'filled_at' => now(),
            ]);

            if ($data['next_action'] === 'lanjut') {
                return MitraAssignmentSesi::create([
                    'mitra_assignment_id' => $mitraAssignment->id,
                    'urutan' => $sesi->urutan + 1,
                    'jadwal_zoom' => $data['next_jadwal'],
                    'status' => 'terjadwal',
                ]);
            }

            $mitraAssignment->update(['status' => 'selesai']);

            return null;
        });

        // Sesi yang baru selesai gak butuh event-nya tetap ada di kalender.
        $calendarWarning = $this->deleteCalendarEvent($sesi);
        if ($sesiBaru) {
            $calendarWarning .= $this->syncSesiToCalendar($sesiBaru->fresh('assignment.mitra'));
        }

        return redirect()->route('assignment.index')->with('status', 'Hasil '.$sesi->label().' tersimpan.'.$calendarWarning);
    }

    public function destroy(MitraAssignment $mitraAssignment): RedirectResponse
    {
        $nama = $mitraAssignment->mitra->nama ?? 'mitra ini';
        foreach ($mitraAssignment->sesis as $sesi) {
            $this->deleteCalendarEvent($sesi);
        }
        $mitraAssignment->delete(); // cascade hapus semua sesis (FK cascadeOnDelete)

        return redirect()->route('assignment.index')->with('status', 'Assignment '.$nama.' dihapus. Mitra ini bisa dijadwalkan ulang lagi dari Data Development.');
    }

    /**
     * Bikin/update event Google Calendar buat satu sesi, simpan google_event_id-nya.
     * Return string kosong kalau sukses (atau Calendar belum di-connect, bukan error),
     * atau pesan warning singkat yang ditempel ke notif "status" kalau beneran gagal —
     * sengaja TIDAK melempar exception, kegagalan sync gak boleh batalkan data assignment.
     */
    private function syncSesiToCalendar(MitraAssignmentSesi $sesi): string
    {
        $assignment = $sesi->assignment;
        $mitra = $assignment->mitra;
        $title = 'Zoom Coaching — '.($mitra->nama ?? 'Mitra').' ('.$sesi->label().')';
        $description = "Status Bulan: {$assignment->status_bulan}\nPeriode: {$assignment->periodeLabel()}\nKode Mitra: ".($mitra->kode_mitra ?? '—');
        $attendees = $this->attendeeEmailsFor($mitra);

        try {
            if ($sesi->google_event_id) {
                $this->calendar->updateEvent($sesi->google_event_id, $title, $description, $sesi->jadwal_zoom, $attendees);

                return '';
            }

            $eventId = $this->calendar->createEvent($title, $description, $sesi->jadwal_zoom, $attendees);
            if ($eventId) {
                $sesi->update(['google_event_id' => $eventId]);
            }

            return '';
        } catch (\Throwable $e) {
            Log::warning('AssignmentController: gagal sync Google Calendar', ['sesi_id' => $sesi->id, 'error' => $e->getMessage()]);

            return ' (Peringatan: gagal sync ke Google Calendar — '.$e->getMessage().')';
        }
    }

    private function deleteCalendarEvent(MitraAssignmentSesi $sesi): string
    {
        if (! $sesi->google_event_id) {
            return '';
        }

        try {
            $this->calendar->deleteEvent($sesi->google_event_id);
        } catch (\Throwable $e) {
            Log::warning('AssignmentController: gagal hapus event Google Calendar', ['sesi_id' => $sesi->id, 'error' => $e->getMessage()]);

            return ' (Peringatan: gagal hapus event di Google Calendar — '.$e->getMessage().')';
        }

        return '';
    }

    /**
     * Email yang diundang sebagai attendee: KAE pemilik mitra ini (kalau ada —
     * Mitra sendiri gak punya kolom email) + email akun Google yang connect
     * (kemitraan.srn@gmail.com), biar akun itu juga kebagian notif undangan,
     * bukan cuma "diam-diam" muncul di kalendernya sendiri selaku organizer.
     */
    private function attendeeEmailsFor($mitra): array
    {
        $emails = [];

        if ($mitra?->kae_code) {
            $kaeEmail = User::where('role', 'kae')->where('kae_code', $mitra->kae_code)->value('email');
            if ($kaeEmail) {
                $emails[] = $kaeEmail;
            }
        }

        if ($connected = $this->calendar->connectedEmail()) {
            $emails[] = $connected;
        }

        return $emails;
    }
}
