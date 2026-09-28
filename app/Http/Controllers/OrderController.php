<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('order.index', [
            'orders' => $this->filtered($request)->latest('tanggal_order')->paginate(25)->withQueryString(),
        ]);
    }

    /**
     * Excel-nya kolomnya disamain persis sama sheet "Master Transaksi" di
     * template upload Order Harian (lihat ImportTemplateService::orderHarian())
     * biar file hasil download ini bisa langsung dipakai ulang buat upload
     * kalau perlu — bukan sekadar laporan sekali lihat.
     */
    public function export(Request $request): StreamedResponse
    {
        $orders = $this->filtered($request)->latest('tanggal_order')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Transaksi');

        $headers = [
            'TANGGAL', 'BULAN ORDER', 'ID TRANSAKSI (Perpack)', 'RESELLER', 'NAME', 'ADDRESS',
            'QTY', 'TOTAL', 'DISKON', 'DISKON CLAIM', 'DISKON RETURN', 'BIAYA PENDAFTARAN', 'DISKON RETURN ID',
            'ONGKIR', 'BIAYA PENANGANAN', 'TOTAL TRANSFER', 'STATUS PEMBAYARAN', 'STATUS',
        ];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);

        $r = 2;
        foreach ($orders as $o) {
            $sheet->fromArray([
                $o->tanggal_order->format('Y-m-d'),
                $o->tanggal_order->format('F'),
                $o->no_order,
                $o->mitra->kode_mitra ?? '—',
                $o->mitra->nama ?? '—',
                $o->mitra->alamat ?? null,
                $o->qty,
                (float) $o->total_transaksi,
                (float) $o->diskon,
                $o->diskon_claim !== null ? (float) $o->diskon_claim : null,
                $o->diskon_return !== null ? (float) $o->diskon_return : null,
                (float) $o->biaya_pendaftaran,
                $o->diskon_return_id,
                (float) $o->ongkir,
                $o->biaya_penanganan !== null ? (float) $o->biaya_penanganan : null,
                $o->total_transfer !== null ? (float) $o->total_transfer : null,
                $o->status_pembayaran,
                $o->status,
            ], null, 'A'.$r, true);
            $r++;
        }

        foreach (range('A', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'Order Transaksi '.now()->format('d-m-Y').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $user = $request->user();

        return Order::with('mitra')
            ->when(! $user->canViewAll(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('no_order', 'like', '%'.$request->input('q').'%')
                    ->orWhereHas('mitra', fn ($m) => $m->where('nama', 'like', '%'.$request->input('q').'%')
                        ->orWhere('kode_mitra', 'like', '%'.$request->input('q').'%'));
            }))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal_order', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal_order', '<=', $request->input('sampai')))
            ->when($request->filled('status_pembayaran'), fn ($q) => $q->where('status_pembayaran', $request->input('status_pembayaran')));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOrder($request, $order);

        $order->load(['mitra', 'items.produk', 'importBatch', 'editor']);

        return view('order.show', ['order' => $order]);
    }

    public function edit(Request $request, Order $order): View
    {
        $this->authorizeOrder($request, $order);

        return view('order.edit', ['order' => $order]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'tanggal_order' => ['required', 'date'],
            'total_transaksi' => ['required', 'numeric', 'min:0'],
            'status_pembayaran' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'alasan_edit' => ['required', 'string', 'max:500'],
        ]);

        $before = $order->only(['tanggal_order', 'total_transaksi', 'status_pembayaran', 'status']);
        $before['tanggal_order'] = $order->tanggal_order->toDateString();

        $order->update([
            'tanggal_order' => $data['tanggal_order'],
            'total_transaksi' => $data['total_transaksi'],
            'status_pembayaran' => $data['status_pembayaran'],
            'status' => $data['status'],
            'is_edited' => true,
            'edited_by' => $request->user()->id,
            'edited_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'aksi' => 'edit_manual',
            'tabel_terkait' => 'orders',
            'record_id' => $order->id,
            'data_lama' => [...$before, 'alasan' => $data['alasan_edit']],
            'data_baru' => $order->only(['tanggal_order', 'total_transaksi', 'status_pembayaran', 'status']),
        ]);

        return redirect()->route('order.show', $order)->with('status', 'Order berhasil dikoreksi. Perubahan tercatat di riwayat.');
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        if (! $user->canViewAll() && $order->kae_code !== $user->kae_code) {
            abort(403, 'Anda tidak punya akses ke order ini.');
        }
    }
}
