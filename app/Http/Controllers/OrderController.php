<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Order::with('mitra')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('no_order', 'like', '%'.$request->input('q').'%')
                    ->orWhereHas('mitra', fn ($m) => $m->where('nama', 'like', '%'.$request->input('q').'%')
                        ->orWhere('kode_mitra', 'like', '%'.$request->input('q').'%'));
            }))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal_order', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal_order', '<=', $request->input('sampai')))
            ->when($request->filled('status_pembayaran'), fn ($q) => $q->where('status_pembayaran', $request->input('status_pembayaran')))
            ->latest('tanggal_order');

        return view('order.index', [
            'orders' => $query->paginate(25)->withQueryString(),
        ]);
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
            'total_transaksi' => ['required', 'numeric', 'min:0'],
            'status_pembayaran' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'alasan_edit' => ['required', 'string', 'max:500'],
        ]);

        $before = $order->only(['total_transaksi', 'status_pembayaran', 'status']);

        $order->update([
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
            'data_baru' => $order->only(['total_transaksi', 'status_pembayaran', 'status']),
        ]);

        return redirect()->route('order.show', $order)->with('status', 'Order berhasil dikoreksi. Perubahan tercatat di riwayat.');
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        if (! $user->isAdmin() && $order->kae_code !== $user->kae_code) {
            abort(403, 'Anda tidak punya akses ke order ini.');
        }
    }
}
