<?php

namespace App\Http\Controllers;

use App\Models\LmsEnrollment;
use App\Models\LmsStep;
use App\Models\LmsStepCompletion;
use App\Models\Mitra;
use App\Models\SetUpLmsNote;
use App\Models\SpecialDeal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Set Up LMS (Growth Specialist > Special Reg & Reg) — tracking mitra yang
 * sudah diprofiling lalu mengerjakan LMS per platform (Shopee/Meta/TikTok).
 * Tiap video/langkah harus dicentang + wajib isi link GDrive sebagai bukti
 * mitra sudah mengerjakan sesuai videonya. Daftar langkah ada di tabel
 * lms_steps (bukan konstanta) karena nanti dikelola admin.
 */
class SetUpLmsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tabInput = (string) $request->input('tab');
        // 'semua' = pseudo-tab agregat (dipakai link funnel Dashboard,
        // lihat filteredRowsSemuaPlatform()) -- BUKAN platform asli dari
        // LmsStep::PLATFORMS, jadi gak ditambahkan ke konstanta itu.
        $tab = $tabInput === 'semua' ? 'semua' : (array_key_exists($tabInput, LmsStep::PLATFORMS) ? $tabInput : 'shopee');
        $steps = $tab === 'semua' ? collect() : LmsStep::aktifUntuk($tab)->get();

        $mitraOptions = $tab === 'semua' ? collect() : $this->scopedMitra($user)->where('status', 'aktif')->orderBy('nama')->get(['id', 'nama', 'kode_mitra'])
            ->map(fn ($m) => (object) [
                'id' => $m->id,
                'label' => $m->nama.' ('.$m->kode_mitra.')',
                'url' => route('growth-specialist.set-up-lms', ['tab' => $tab, 'mitra_id' => $m->id]).'#input',
            ]);

        // Panel checklist video per-mitra gak relevan lintas platform, jadi
        // dilewati sama sekali kalau tab=semua (lihat set-up-lms/index.blade.php).
        $selectedMitra = null;
        $selectedCompletions = collect();
        $isEnrolled = false;
        if ($tab !== 'semua' && $request->filled('mitra_id')) {
            $selectedMitra = $this->scopedMitra($user)->find($request->integer('mitra_id'));
        }
        if ($selectedMitra) {
            $isEnrolled = LmsEnrollment::where('mitra_id', $selectedMitra->id)->where('platform', $tab)->exists();
            $selectedCompletions = LmsStepCompletion::where('mitra_id', $selectedMitra->id)
                ->whereIn('lms_step_id', $steps->pluck('id'))->get()->keyBy('lms_step_id');
        }

        $q = trim((string) $request->input('q'));
        $status = trim((string) $request->input('status'));
        $rows = $this->filteredRows($request, $tab, $steps);

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $rowsPage = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $selectedPct = $steps->count() > 0 ? (int) round($selectedCompletions->count() / $steps->count() * 100) : 0;

        return view('set-up-lms.index', [
            'tab' => $tab,
            'platforms' => LmsStep::PLATFORMS,
            'steps' => $steps,
            'mitraOptions' => $mitraOptions,
            'selectedMitra' => $selectedMitra,
            'selectedCompletions' => $selectedCompletions,
            'selectedPct' => $selectedPct,
            'selectedStatus' => self::statusLabel($selectedPct),
            'isEnrolled' => $isEnrolled,
            'rowsPage' => $rowsPage,
            'q' => $q,
            'status' => $status,
            'kaeOptions' => $user->canViewAll() ? User::where('role', 'kae')->orderBy('name')->get() : collect(),
        ]);
    }

    public function enroll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'integer'],
            'platform' => ['required', 'in:'.implode(',', array_keys(LmsStep::PLATFORMS))],
        ]);

        $mitra = $this->scopedMitra($request->user())->findOrFail($data['mitra_id']);
        LmsEnrollment::firstOrCreate(['mitra_id' => $mitra->id, 'platform' => $data['platform']]);

        return $this->backToMitra($data['platform'], $mitra->id, $mitra->nama.' didaftarkan ke LMS '.LmsStep::PLATFORMS[$data['platform']].'.');
    }

    /** Hapus mitra dari tracking LMS satu platform beserta semua centang & link GDrive-nya. */
    public function destroyEnrollment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'integer'],
            'platform' => ['required', 'in:'.implode(',', array_keys(LmsStep::PLATFORMS))],
        ]);

        $mitra = $this->scopedMitra($request->user())->findOrFail($data['mitra_id']);

        DB::transaction(function () use ($mitra, $data) {
            LmsStepCompletion::where('mitra_id', $mitra->id)
                ->whereIn('lms_step_id', LmsStep::where('platform', $data['platform'])->pluck('id'))
                ->delete();
            LmsEnrollment::where('mitra_id', $mitra->id)->where('platform', $data['platform'])->delete();
        });

        return redirect()->route('growth-specialist.set-up-lms', ['tab' => $data['platform']])
            ->with('status', $mitra->nama.' dihapus dari LMS '.LmsStep::PLATFORMS[$data['platform']].'.');
    }

    public function storeStep(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'integer'],
            'lms_step_id' => ['required', 'integer', 'exists:lms_steps,id'],
            'link_gdrive' => ['required', 'url', 'max:500'],
        ], [
            'link_gdrive.required' => 'Link GDrive wajib diisi sebagai bukti mitra sudah mengerjakan.',
            'link_gdrive.url' => 'Link GDrive harus berupa URL yang valid (diawali https://).',
        ]);

        $mitra = $this->scopedMitra($request->user())->findOrFail($data['mitra_id']);
        $step = LmsStep::where('aktif', true)->findOrFail($data['lms_step_id']);

        LmsEnrollment::firstOrCreate(['mitra_id' => $mitra->id, 'platform' => $step->platform]);
        LmsStepCompletion::updateOrCreate(
            ['mitra_id' => $mitra->id, 'lms_step_id' => $step->id],
            ['link_gdrive' => $data['link_gdrive'], 'completed_by' => $request->user()->id]
        );

        return $this->backToMitra($step->platform, $mitra->id, 'Video '.$step->urutan.' ('.$step->judul.') ditandai selesai.');
    }

    public function destroyStep(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'integer'],
            'lms_step_id' => ['required', 'integer', 'exists:lms_steps,id'],
        ]);

        $mitra = $this->scopedMitra($request->user())->findOrFail($data['mitra_id']);
        $step = LmsStep::findOrFail($data['lms_step_id']);

        LmsStepCompletion::where('mitra_id', $mitra->id)->where('lms_step_id', $step->id)->delete();

        return $this->backToMitra($step->platform, $mitra->id, 'Centang video '.$step->urutan.' ('.$step->judul.') dibatalkan.');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'integer'],
            'platform' => ['required', 'in:'.implode(',', array_keys(LmsStep::PLATFORMS))],
            'catatan' => ['required', 'string', 'max:2000'],
        ]);

        $mitra = $this->scopedMitra($request->user())->findOrFail($data['mitra_id']);

        SetUpLmsNote::updateOrCreate(
            ['mitra_id' => $mitra->id, 'platform' => $data['platform']],
            ['catatan' => $data['catatan'], 'created_by' => $request->user()->id]
        );

        return redirect()->route('growth-specialist.set-up-lms', ['tab' => $data['platform']])
            ->with('status', 'Catatan untuk '.$mitra->nama.' tersimpan.');
    }

    /**
     * Excel-nya kolomnya disamain persis sama tabel Progres LMS di halaman
     * ini -- satu kolom per video (centang/kosong + link GDrive-nya) plus
     * kolom Catatan di akhir, biar bisa dicek lengkap tanpa buka SRN.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $tab = array_key_exists((string) $request->input('tab'), LmsStep::PLATFORMS) ? $request->input('tab') : 'shopee';
        $platformLabel = LmsStep::PLATFORMS[$tab];
        $steps = LmsStep::aktifUntuk($tab)->get();
        $rows = $this->filteredRows($request, $tab, $steps);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Set Up LMS '.$platformLabel);

        $header = ['ID Mitra', 'Nama Mitra', 'KAE', 'Segmen'];
        foreach ($steps as $step) {
            $header[] = $step->urutan.'. '.$step->judul;
        }
        $header[] = '% Selesai';
        $header[] = 'Status';
        $header[] = 'Catatan';
        $sheet->fromArray($header, null, 'A1', true);
        $lastCol = Coordinate::stringFromColumnIndex(count($header));
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

        $r = 2;
        foreach ($rows as $row) {
            $line = [
                $row->mitra->kode_mitra,
                $row->mitra->nama,
                $row->kae,
                $row->segmen,
            ];
            foreach ($steps as $step) {
                $completion = $row->completions->get($step->id);
                $line[] = $completion?->link_gdrive;
            }
            $line[] = $row->pct.'%';
            $line[] = $row->status;
            $line[] = $row->note->catatan ?? null;

            $sheet->fromArray($line, null, 'A'.$r, true);
            $r++;
        }

        for ($i = 1; $i <= count($header); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $filename = 'set_up_lms_'.$tab.'_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Filter/urutan/status-komputasi dipakai bareng oleh index() (dipaginasi)
     * dan export() (semua baris) -- satu sumber kebenaran.
     */
    private function filteredRows(Request $request, string $tab, Collection $steps): Collection
    {
        $user = $request->user();
        $q = trim((string) $request->input('q'));
        $status = trim((string) $request->input('status'));

        if ($tab === 'semua') {
            return $this->filteredRowsSemuaPlatform($request, $user, $q, $status);
        }

        $enrolledIds = LmsEnrollment::where('platform', $tab)->pluck('mitra_id');
        $mitraRows = $this->scopedMitra($user)
            ->whereIn('id', $enrolledIds)
            ->when($q !== '', fn ($qr) => $qr->where(fn ($w) => $w->where('nama', 'like', "%{$q}%")->orWhere('kode_mitra', 'like', "%{$q}%")))
            ->when($user->canViewAll() && $request->filled('kae_code'), fn ($qr) => $qr->where('kae_code', $request->input('kae_code')))
            ->orderBy('nama')
            ->get();

        $completionsByMitra = LmsStepCompletion::whereIn('mitra_id', $mitraRows->pluck('id'))
            ->whereIn('lms_step_id', $steps->pluck('id'))
            ->get()
            ->groupBy('mitra_id')
            ->map(fn ($c) => $c->keyBy('lms_step_id'));

        $segmenMap = SpecialDeal::segmenByMitraId(now());
        $kaeMap = User::kaeNameMap();
        $notesByMitra = SetUpLmsNote::where('platform', $tab)
            ->whereIn('mitra_id', $mitraRows->pluck('id'))->get()->keyBy('mitra_id');

        $rows = $mitraRows->map(function (Mitra $m) use ($completionsByMitra, $steps, $segmenMap, $kaeMap, $notesByMitra) {
            $done = $completionsByMitra->get($m->id, collect());
            $pct = $steps->count() > 0 ? (int) round($done->count() / $steps->count() * 100) : 0;

            return (object) [
                'mitra' => $m,
                'kae' => $m->kae_code ? ($kaeMap[$m->kae_code] ?? $m->kae_code) : null,
                'segmen' => $segmenMap[$m->id] ?? null,
                'completions' => $done,
                'pct' => $pct,
                'status' => self::statusLabel($pct),
                'note' => $notesByMitra->get($m->id),
            ];
        });

        if ($status !== '') {
            $rows = $rows->where('status', $status)->values();
        }

        return $rows;
    }

    /**
     * Mode agregat "Semua Platform" (?tab=semua) -- dipakai link funnel
     * Dashboard (tahap "Masuk Set Up LMS"/"LMS Lengkap"), beda dari mode
     * per-tab di atas: status per mitra dari LmsStep::statusByMitra()
     * (Lengkap di MANAPUN platform), roster-nya semua mitra aktif
     * terscope KAE (bukan cuma yang enrolled di 1 platform tertentu).
     *
     * `status` terima 2 pseudo-value tambahan khusus mode ini:
     * - 'belum-masuk' -- mitra yang gak ada LmsEnrollment di platform manapun.
     * - 'belum-lengkap' -- enrolled tapi status agregatnya Proses/Awal (gabungan).
     */
    private function filteredRowsSemuaPlatform(Request $request, User $user, string $q, string $status): Collection
    {
        $statusByMitra = LmsStep::statusByMitra();
        $enrolledIds = $statusByMitra->keys();
        $belumMasuk = $status === 'belum-masuk';

        $mitraRows = $this->scopedMitra($user)->where('status', 'aktif')
            ->when($belumMasuk, fn ($qr) => $qr->whereNotIn('id', $enrolledIds), fn ($qr) => $qr->whereIn('id', $enrolledIds))
            ->when($q !== '', fn ($qr) => $qr->where(fn ($w) => $w->where('nama', 'like', "%{$q}%")->orWhere('kode_mitra', 'like', "%{$q}%")))
            ->when($user->canViewAll() && $request->filled('kae_code'), fn ($qr) => $qr->where('kae_code', $request->input('kae_code')))
            ->orderBy('nama')
            ->get();

        $segmenMap = SpecialDeal::segmenByMitraId(now());
        $kaeMap = User::kaeNameMap();

        $rows = $mitraRows->map(fn (Mitra $m) => (object) [
            'mitra' => $m,
            'kae' => $m->kae_code ? ($kaeMap[$m->kae_code] ?? $m->kae_code) : null,
            'segmen' => $segmenMap[$m->id] ?? null,
            'completions' => collect(),
            'pct' => null,
            'status' => $statusByMitra->get($m->id) ?? 'Belum Masuk',
            'note' => null,
        ]);

        if ($status === 'belum-lengkap') {
            $rows = $rows->whereIn('status', ['Proses', 'Awal'])->values();
        } elseif ($status !== '' && ! $belumMasuk) {
            $rows = $rows->where('status', $status)->values();
        }

        return $rows;
    }

    private function scopedMitra(User $user)
    {
        return Mitra::query()->when($user->role === 'kae', fn ($q) => $q->where('kae_code', $user->kae_code));
    }

    private function backToMitra(string $platform, int $mitraId, string $message): RedirectResponse
    {
        return redirect()->to(route('growth-specialist.set-up-lms', ['tab' => $platform, 'mitra_id' => $mitraId]).'#input')->with('status', $message);
    }

    private static function statusLabel(int $pct): string
    {
        return $pct >= LmsStep::LENGKAP_THRESHOLD_PCT ? 'Lengkap' : ($pct > 0 ? 'Proses' : 'Awal');
    }
}
