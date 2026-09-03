<?php

namespace App\Http\Controllers;

use App\Models\ArPayment;
use App\Models\ArReceivable;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Data Piutang / AR": admin picks an already-confirmed order and records
 * how much of it is still owed (jumlah AR is entered manually — it doesn't
 * have to equal the order total, e.g. when part was already paid cash) plus
 * a due date of now+14 or now+30 days. Payments are logged one at a time
 * (cicilan), and the AR is "Lunas" once payments cover jumlah_ar. Admin
 * does the initial input; KAE can view and record payments for their own
 * mitra only.
 */
class ArController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $orderResults = collect();
        if ($user->isAdmin() && ($request->filled('cari_no_order') || $request->filled('cari_dari') || $request->filled('cari_sampai'))) {
            $orderResults = Order::query()
                ->with('mitra:id,nama,kode_mitra')
                ->whereDoesntHave('arReceivable')
                ->when($request->filled('cari_no_order'), fn ($q) => $q->where('no_order', 'like', '%'.$request->input('cari_no_order').'%'))
                ->when($request->filled('cari_dari'), fn ($q) => $q->whereDate('tanggal_order', '>=', $request->input('cari_dari')))
                ->when($request->filled('cari_sampai'), fn ($q) => $q->whereDate('tanggal_order', '<=', $request->input('cari_sampai')))
                ->orderByDesc('tanggal_order')
                ->limit(50)
                ->get();
        }

        $arList = $this->buildArList($user);

        $belumLunas = $arList->where('lunas', false);
        $jatuhTempo = $belumLunas->where('jatuh_tempo_flag', true);
        $belumJatuhTempo = $belumLunas->where('jatuh_tempo_flag', false);

        $totalSisa = $belumLunas->sum('sisa_ar');
        $jatuhTempoSisa = $jatuhTempo->sum('sisa_ar');
        $belumJatuhTempoSisa = $belumJatuhTempo->sum('sisa_ar');
        $totalCount = $belumLunas->count();
        $jatuhTempoCount = $jatuhTempo->count();
        $belumJatuhTempoCount = $belumJatuhTempo->count();

        $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

        $stats = [
            'total_count' => $totalCount,
            'total_sisa' => $totalSisa,
            'jatuh_tempo_count' => $jatuhTempoCount,
            'jatuh_tempo_sisa' => $jatuhTempoSisa,
            'jatuh_tempo_pct' => $pct($jatuhTempoSisa, $totalSisa),
            'belum_jatuh_tempo_count' => $belumJatuhTempoCount,
            'belum_jatuh_tempo_sisa' => $belumJatuhTempoSisa,
            'belum_jatuh_tempo_pct' => $pct($belumJatuhTempoSisa, $totalSisa),
        ];

        $agingBuckets = collect(['1-14', '15-30', '31-60', '61+'])->map(function ($bucket) use ($jatuhTempo, $jatuhTempoSisa, $pct) {
            $rows = $jatuhTempo->where('aging_bucket', $bucket);
            $sisa = $rows->sum('sisa_ar');

            return [
                'bucket' => $bucket,
                'count' => $rows->count(),
                'sisa' => $sisa,
                'pct' => $pct($sisa, $jatuhTempoSisa),
            ];
        });

        $status = $request->input('status');
        $arList = $arList
            ->when($status === 'belum-lunas', fn ($c) => $c->where('lunas', false))
            ->when($status === 'lunas', fn ($c) => $c->where('lunas', true))
            ->when($status === 'jatuh-tempo', fn ($c) => $c->where('jatuh_tempo_flag', true))
            ->sortBy([['jatuh_tempo_flag', 'desc'], ['tanggal_jatuh_tempo', 'asc']])
            ->values();

        return view('ar.index', [
            'orderResults' => $orderResults,
            'arList' => $arList,
            'stats' => $stats,
            'agingBuckets' => $agingBuckets,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, ArReceivable> */
    private function buildArList($user): \Illuminate\Support\Collection
    {
        return ArReceivable::with(['order.mitra:id,nama,kode_mitra,kae_code', 'payments.creator:id,name'])
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('order.mitra', fn ($qq) => $qq->where('kae_code', $user->kae_code)))
            ->get()
            ->map(function ($ar) {
                $ar->sisa_ar = $ar->sisa();
                $ar->lunas = $ar->isLunas();
                $ar->jatuh_tempo_flag = $ar->isJatuhTempo();
                $ar->hari_terlambat = $ar->hariTerlambat();
                $ar->aging_bucket = $ar->agingBucket();

                return $ar;
            });
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $status = $request->input('status');

        $arList = $this->buildArList($user)
            ->when($status === 'belum-lunas', fn ($c) => $c->where('lunas', false))
            ->when($status === 'lunas', fn ($c) => $c->where('lunas', true))
            ->when($status === 'jatuh-tempo', fn ($c) => $c->where('jatuh_tempo_flag', true))
            ->sortBy([['jatuh_tempo_flag', 'desc'], ['tanggal_jatuh_tempo', 'asc']])
            ->values();

        $statusLabel = fn ($ar) => $ar->lunas ? 'Lunas' : ($ar->jatuh_tempo_flag ? 'Jatuh Tempo' : 'Belum Jatuh Tempo');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Sheet 1: satu baris per AR (ringkasan) — jumlah, sudah dibayar,
        // sisa, jatuh tempo, status.
        $sheetAr = $spreadsheet->createSheet();
        $sheetAr->setTitle('Piutang');
        $headersAr = ['Kode Mitra', 'Nama Mitra', 'KAE', 'No Order', 'Tanggal Order', 'Jumlah AR', 'Total Dibayar', 'Sisa AR', 'Tanggal Input', 'Termin (hari)', 'Tanggal Jatuh Tempo', 'Status', 'Hari Terlambat', 'Jumlah Cicilan'];
        $sheetAr->fromArray($headersAr, null, 'A1', true);
        $sheetAr->getStyle('A1:N1')->getFont()->setBold(true);
        $sheetAr->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBE5EF');

        $r = 2;
        foreach ($arList as $ar) {
            $mitra = $ar->order->mitra;
            $sheetAr->fromArray([
                $mitra->kode_mitra ?? '—',
                $mitra->nama ?? '—',
                $mitra->kae_code ?? '—',
                $ar->order->no_order,
                optional($ar->order->tanggal_order)->format('d/m/Y'),
                (float) $ar->jumlah_ar,
                $ar->totalDibayar(),
                $ar->sisa_ar,
                $ar->tanggal_input->format('d/m/Y'),
                (int) $ar->jatuh_tempo_hari,
                $ar->tanggal_jatuh_tempo->format('d/m/Y'),
                $statusLabel($ar),
                $ar->hari_terlambat,
                $ar->payments->count(),
            ], null, 'A'.$r, true);
            $r++;
        }
        foreach (range('A', 'N') as $col) {
            $sheetAr->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet 2: satu baris per CICILAN (detail pembayaran).
        $sheetCicilan = $spreadsheet->createSheet();
        $sheetCicilan->setTitle('Cicilan');
        $headersCicilan = ['Kode Mitra', 'Nama Mitra', 'No Order', 'Cicilan Ke', 'Tanggal Bayar', 'Jumlah Bayar', 'Dicatat Oleh'];
        $sheetCicilan->fromArray($headersCicilan, null, 'A1', true);
        $sheetCicilan->getStyle('A1:G1')->getFont()->setBold(true);
        $sheetCicilan->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBE5EF');

        $r = 2;
        foreach ($arList as $ar) {
            $mitra = $ar->order->mitra;
            foreach ($ar->payments as $p) {
                $sheetCicilan->fromArray([
                    $mitra->kode_mitra ?? '—',
                    $mitra->nama ?? '—',
                    $ar->order->no_order,
                    $p->cicilan_ke,
                    $p->tanggal_bayar->format('d/m/Y'),
                    (float) $p->jumlah_bayar,
                    $p->creator->name ?? '—',
                ], null, 'A'.$r, true);
                $r++;
            }
        }
        foreach (range('A', 'G') as $col) {
            $sheetCicilan->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Data_Piutang_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'jumlah_ar' => ['required', 'integer', 'min:1'],
            'jatuh_tempo_hari' => ['required', 'in:14,30'],
        ]);

        if (ArReceivable::where('order_id', $data['order_id'])->exists()) {
            return back()->withErrors(['order_id' => 'Order ini sudah punya data AR.']);
        }

        $tanggalInput = now()->toDateString();

        ArReceivable::create([
            'order_id' => $data['order_id'],
            'jumlah_ar' => $data['jumlah_ar'],
            'tanggal_input' => $tanggalInput,
            'jatuh_tempo_hari' => $data['jatuh_tempo_hari'],
            'tanggal_jatuh_tempo' => now()->addDays((int) $data['jatuh_tempo_hari'])->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('ar.index')->with('status', 'Data AR berhasil disimpan.');
    }

    public function update(Request $request, ArReceivable $arReceivable): RedirectResponse
    {
        $arReceivable->loadMissing('payments');
        $sudahDibayar = (int) $arReceivable->totalDibayar();

        $data = $request->validate([
            'jumlah_ar' => ['required', 'integer', 'min:'.max(1, $sudahDibayar)],
            'jatuh_tempo_hari' => ['required', 'in:14,30'],
        ], [
            'jumlah_ar.min' => 'Jumlah AR tidak boleh kurang dari total yang sudah dibayar (Rp'.number_format($sudahDibayar, 0, ',', '.').').',
        ]);

        $arReceivable->update([
            'jumlah_ar' => $data['jumlah_ar'],
            'jatuh_tempo_hari' => $data['jatuh_tempo_hari'],
            'tanggal_jatuh_tempo' => $arReceivable->tanggal_input->copy()->addDays((int) $data['jatuh_tempo_hari'])->toDateString(),
        ]);

        return redirect()->route('ar.index')->with('status', 'Data AR berhasil diperbarui.');
    }

    public function pay(Request $request, ArReceivable $arReceivable): RedirectResponse
    {
        $user = $request->user();
        $arReceivable->loadMissing('order.mitra', 'payments');

        if (! $user->isAdmin() && $arReceivable->order->mitra->kae_code !== $user->kae_code) {
            throw new HttpException(403, 'Kamu tidak punya akses ke AR mitra ini.');
        }

        if ($arReceivable->isLunas()) {
            return back()->withErrors(['jumlah_bayar' => 'AR ini sudah lunas.']);
        }

        $data = $request->validate([
            'jumlah_bayar' => ['required', 'integer', 'min:1', 'max:'.(int) $arReceivable->sisa()],
            'tanggal_bayar' => ['required', 'date'],
        ]);

        $arReceivable->payments()->create([
            'cicilan_ke' => $arReceivable->payments->count() + 1,
            'jumlah_bayar' => $data['jumlah_bayar'],
            'tanggal_bayar' => $data['tanggal_bayar'],
            'created_by' => $user->id,
        ]);

        return redirect()->route('ar.index')->with('status', 'Pembayaran AR berhasil dicatat.');
    }

    public function destroy(ArReceivable $arReceivable): RedirectResponse
    {
        $arReceivable->delete();

        return redirect()->route('ar.index')->with('status', 'Data AR berhasil dihapus.');
    }

    public function deletePayment(Request $request, ArPayment $payment): RedirectResponse
    {
        $user = $request->user();
        $payment->loadMissing('arReceivable.order.mitra');
        $arReceivable = $payment->arReceivable;

        if (! $user->isAdmin() && $arReceivable->order->mitra->kae_code !== $user->kae_code) {
            throw new HttpException(403, 'Kamu tidak punya akses ke AR mitra ini.');
        }

        DB::transaction(function () use ($payment, $arReceivable) {
            $payment->delete();

            $arReceivable->payments()->orderBy('cicilan_ke')->get()
                ->values()
                ->each(fn ($p, $i) => $p->update(['cicilan_ke' => $i + 1]));
        });

        return redirect()->route('ar.index')->with('status', 'Cicilan berhasil dihapus.');
    }
}
