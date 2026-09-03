<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\SpecialDeal;
use App\Services\ImportTemplateService;
use App\Services\MouDocumentService;
use App\Services\SpecialDealImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpecialDealController extends Controller
{
    public const STATUSES = ['proses', 'done', 'batal'];

    private const SEGMEN_DISPLAY_ORDER = ['PARETO', 'RTP', 'SPECIAL REGULER', 'REGULER'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $kuartal = $request->integer('kuartal') ?: now()->quarter;
        $tahun = $request->integer('tahun') ?: now()->year;

        $deals = SpecialDeal::with(['mitra', 'kae'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_user_id', $user->id))
            ->where('kuartal', $kuartal)
            ->where('tahun', $tahun)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->get()
            ->sortBy(fn ($d) => $d->mitra->nama ?? '')
            ->values();

        SpecialDeal::attachAktual($deals);

        $bulanAwal = ($kuartal - 1) * 3 + 1;
        $bulanLabels = collect([$bulanAwal, $bulanAwal + 1, $bulanAwal + 2])
            ->mapWithKeys(fn ($b) => [$b => \Carbon\Carbon::create($tahun, $b, 1)->translatedFormat('M')]);

        $groups = $deals->groupBy(fn ($d) => $d->segmen ?: 'Lainnya')->map(function ($group) {
            $targetSum = $group->sum('target_kuartal');
            $qSdSum = $group->sum('q_sd');

            return [
                'deals' => $group,
                'summary' => [
                    'count' => $group->count(),
                    'done_count' => $group->where('status', 'done')->count(),
                    'target_sum' => $targetSum,
                    'nom_sum' => $group->sum(fn ($d) => $d->nominalReward() ?? 0),
                    'q_sd_sum' => $qSdSum,
                    'ach_pct' => $targetSum > 0 ? round($qSdSum / $targetSum * 100, 1) : null,
                    'gap_sum' => $qSdSum - $targetSum,
                ],
            ];
        })->sortBy(function ($group, $segmen) {
            $pos = array_search($segmen, self::SEGMEN_DISPLAY_ORDER, true);

            return $pos === false ? count(self::SEGMEN_DISPLAY_ORDER) : $pos;
        });

        return view('special-deal.index', [
            'groups' => $groups,
            'bulanLabels' => $bulanLabels,
            'kuartal' => $kuartal,
            'tahun' => $tahun,
            'totalCount' => $deals->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $mitraOptions = Mitra::where('status', 'aktif')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get();

        return view('special-deal.form', [
            'deal' => new SpecialDeal(),
            'mitraOptions' => $mitraOptions,
            'selectedMitraId' => $request->integer('mitra_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $mitra = Mitra::findOrFail($data['mitra_id']);
        $this->authorizeMitra($request, $mitra);

        SpecialDeal::create([
            ...$data,
            'kae_user_id' => $user->id,
        ]);

        return redirect()->route('mitra.show', $mitra)->with('status', 'Special deal berhasil diajukan.');
    }

    public function edit(Request $request, SpecialDeal $specialDeal): View
    {
        $this->authorizeMitra($request, $specialDeal->mitra);

        $user = $request->user();
        $mitraOptions = Mitra::where('status', 'aktif')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get();

        return view('special-deal.form', [
            'deal' => $specialDeal,
            'mitraOptions' => $mitraOptions,
            'selectedMitraId' => $specialDeal->mitra_id,
        ]);
    }

    public function update(Request $request, SpecialDeal $specialDeal): RedirectResponse
    {
        $this->authorizeMitra($request, $specialDeal->mitra);

        $data = $this->validated($request);
        $mitra = Mitra::findOrFail($data['mitra_id']);
        $this->authorizeMitra($request, $mitra);

        $specialDeal->update($data);

        return redirect()->route('special-deal.index')->with('status', 'Special deal berhasil diperbarui.');
    }

    public function destroy(SpecialDeal $specialDeal): RedirectResponse
    {
        $specialDeal->delete();

        return back()->with('status', 'Special deal berhasil dihapus.');
    }

    public function mou(Request $request, SpecialDeal $specialDeal): BinaryFileResponse
    {
        $this->authorizeMitra($request, $specialDeal->mitra);

        $phpWord = MouDocumentService::build($specialDeal);
        $fileName = MouDocumentService::fileName($specialDeal);

        $tempFile = tempnam(sys_get_temp_dir(), 'mou').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = (new ImportTemplateService())->specialDeal();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template_special_deal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function showUpload(): View
    {
        return view('special-deal.upload');
    }

    public function upload(Request $request, SpecialDealImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['file' => 'File']);

        $result = $importer->import($request->file('file'), $request->user());

        if (! $result['ok']) {
            return back()->withErrors(['file' => implode(' ', $result['errors'])]);
        }

        $jumlahSkip = count($result['skipped']);
        $redirect = redirect()->route('special-deal.index')
            ->with('status', 'Upload Special Deal: '.$result['jumlah_tersimpan'].' dari '.$result['jumlah_baris'].' baris tersimpan.'.($jumlahSkip > 0 ? ' '.$jumlahSkip.' baris dilewati.' : ''));

        if ($jumlahSkip > 0) {
            $redirect->with('import_skipped', $result['skipped']);
        }

        return $redirect;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'segmen' => ['required', 'string', 'max:30'],
            'deskripsi' => ['required', 'string'],
            'target_kuartal' => ['required', 'numeric', 'min:0'],
            'budget_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'subsidi' => ['required', 'string', 'max:50'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'kuartal' => ['required', 'integer', 'between:1,4'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
        ]);
    }

    private function authorizeMitra(Request $request, Mitra $mitra): void
    {
        $user = $request->user();

        if (! $user->isAdmin() && $mitra->kae_code !== $user->kae_code) {
            abort(403, 'Anda tidak punya akses ke mitra ini.');
        }
    }
}
