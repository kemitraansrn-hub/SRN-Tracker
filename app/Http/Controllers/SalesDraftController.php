<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\Produk;
use App\Models\SalesDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Input Penjualan": a scratch calculator KAE use to total up an order with
 * many SKUs before actually placing it. Deliberately not linked to
 * orders/order_items — nothing here counts as real sales data.
 */
class SalesDraftController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $drafts = SalesDraft::with('mitra:id,nama,kode_mitra', 'items')
            ->when(! $user->canViewAll(), fn ($q) => $q->where('created_by', $user->id))
            ->latest()
            ->get();

        return view('sales-draft.index', ['drafts' => $drafts]);
    }

    public function create(Request $request): View
    {
        [$mitraList, $produkList] = $this->formOptions($request->user());

        return view('sales-draft.form', [
            'draft' => null,
            'mitraList' => $mitraList,
            'produkList' => $produkList,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDraft($request);

        DB::transaction(function () use ($data, $request) {
            $draft = SalesDraft::create([
                'mitra_id' => $data['mitra_id'],
                'tanggal_order' => $data['tanggal_order'],
                'diskon_promo' => $data['diskon_promo'] ?? 0,
                'diskon_ongkir' => $data['diskon_ongkir'] ?? 0,
                'ongkir' => $data['ongkir'] ?? 0,
                'grand_total' => $this->calcGrandTotal($data),
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($draft, $data['items']);
        });

        return redirect()->route('sales-draft.index')->with('status', 'Input penjualan berhasil disimpan.');
    }

    public function edit(Request $request, SalesDraft $salesDraft): View
    {
        $this->authorizeOwner($request, $salesDraft);

        $salesDraft->load('items');
        [$mitraList, $produkList] = $this->formOptions($request->user());

        return view('sales-draft.form', [
            'draft' => $salesDraft,
            'mitraList' => $mitraList,
            'produkList' => $produkList,
        ]);
    }

    public function update(Request $request, SalesDraft $salesDraft): RedirectResponse
    {
        $this->authorizeOwner($request, $salesDraft);

        $data = $this->validateDraft($request);

        DB::transaction(function () use ($data, $salesDraft) {
            $salesDraft->update([
                'mitra_id' => $data['mitra_id'],
                'tanggal_order' => $data['tanggal_order'],
                'diskon_promo' => $data['diskon_promo'] ?? 0,
                'diskon_ongkir' => $data['diskon_ongkir'] ?? 0,
                'ongkir' => $data['ongkir'] ?? 0,
                'grand_total' => $this->calcGrandTotal($data),
            ]);

            $salesDraft->items()->delete();
            $this->syncItems($salesDraft, $data['items']);
        });

        return redirect()->route('sales-draft.index')->with('status', 'Input penjualan berhasil diperbarui.');
    }

    public function destroy(Request $request, SalesDraft $salesDraft): RedirectResponse
    {
        $this->authorizeOwner($request, $salesDraft);

        $salesDraft->delete();

        return redirect()->route('sales-draft.index')->with('status', 'Input penjualan berhasil dihapus.');
    }

    private function authorizeOwner(Request $request, SalesDraft $salesDraft): void
    {
        $user = $request->user();

        if (! $user->canViewAll() && $salesDraft->created_by !== $user->id) {
            throw new HttpException(403, 'Kamu tidak punya akses ke input penjualan ini.');
        }
    }

    private function formOptions($user): array
    {
        $mitraList = Mitra::query()
            ->when(! $user->canViewAll(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra', 'alamat']);

        $produkList = Produk::query()
            ->where('status', 'aktif')
            ->orderBy('brand')->orderBy('nama')
            ->get(['id', 'kode_sku', 'nama', 'brand', 'harga']);

        return [$mitraList, $produkList];
    }

    private function validateDraft(Request $request): array
    {
        return $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'tanggal_order' => ['required', 'date'],
            'diskon_promo' => ['nullable', 'numeric', 'min:0'],
            'diskon_ongkir' => ['nullable', 'numeric', 'min:0'],
            'ongkir' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['nullable', 'exists:produk,id'],
            'items.*.nama_produk' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function calcGrandTotal(array $data): float
    {
        $subtotal = collect($data['items'])->sum(fn ($item) => $item['qty'] * $item['harga']);

        return $subtotal - ($data['diskon_promo'] ?? 0) - ($data['diskon_ongkir'] ?? 0) + ($data['ongkir'] ?? 0);
    }

    private function syncItems(SalesDraft $draft, array $items): void
    {
        foreach ($items as $item) {
            $draft->items()->create([
                'produk_id' => $item['produk_id'] ?? null,
                'nama_produk' => $item['nama_produk'],
                'qty' => $item['qty'],
                'harga' => $item['harga'],
                'subtotal' => $item['qty'] * $item['harga'],
            ]);
        }
    }
}
