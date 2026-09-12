<?php

namespace App\Http\Controllers;

use App\Models\BuybackRequest;
use App\Models\BuybackSetting;
use App\Models\Mitra;
use App\Models\Produk;
use App\Services\MitraHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Pengajuan Buy Back": KAE submits a request to buy back near-expiry stock
 * from a mitra. Nilai Buy Back per SKU depreciates from its subtotal by
 * tingkat_penyusutan (a single global rate, admin-controlled via
 * BuybackSetting — never trusted from the client) compounded over the
 * product's age in months: subtotal * (1 - rate)^umur_bulan. AOV is
 * informational only (mitra's YTD average order value) — recomputed
 * server-side at submit time too. Head of SRN (or admin) approves; once
 * approved, the request is locked.
 */
class BuybackRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $requests = BuybackRequest::with(['mitra:id,nama,kode_mitra', 'items', 'creator:id,name', 'headApprover:id,name', 'financeApprover:id,name'])
            ->when(! $user->isAdmin() && ! $user->canActAsHead() && ! $user->isFinance(), fn ($q) => $q->where('created_by', $user->id))
            ->latest()
            ->get();

        return view('buyback.index', ['requests' => $requests]);
    }

    public function create(Request $request): View
    {
        return view('buyback.form', [
            'buybackRequest' => null,
            'mitraList' => $this->mitraOptions($request->user()),
            'produkList' => $this->produkOptions(),
            'currentRate' => BuybackSetting::currentRate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRequest($request);
        $aov = $this->resolveAov($data['mitra_id']);
        $rate = BuybackSetting::currentRate();
        $grand = $this->calcGrandNilai($data['items'], $rate);

        DB::transaction(function () use ($data, $aov, $rate, $grand, $request) {
            $buybackRequest = BuybackRequest::create([
                'mitra_id' => $data['mitra_id'],
                'aov' => $aov,
                'tingkat_penyusutan' => $rate,
                'grand_nilai_beli' => $grand['nilai_beli'],
                'grand_nilai_penyusutan' => $grand['nilai_penyusutan'],
                'grand_nilai_buyback' => $grand['nilai_buyback'],
                'status' => 'on-check',
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($buybackRequest, $data['items'], $rate);
        });

        return redirect()->route('buyback.index')->with('status', 'Pengajuan Buy Back berhasil disimpan.');
    }

    public function edit(Request $request, BuybackRequest $buybackRequest): View
    {
        $this->authorizeEditable($request, $buybackRequest);

        $buybackRequest->load('items');

        return view('buyback.form', [
            'buybackRequest' => $buybackRequest,
            'mitraList' => $this->mitraOptions($request->user()),
            'produkList' => $this->produkOptions(),
            'currentRate' => BuybackSetting::currentRate(),
        ]);
    }

    public function update(Request $request, BuybackRequest $buybackRequest): RedirectResponse
    {
        $this->authorizeEditable($request, $buybackRequest);

        $data = $this->validateRequest($request);
        $aov = $this->resolveAov($data['mitra_id']);
        $rate = BuybackSetting::currentRate();
        $grand = $this->calcGrandNilai($data['items'], $rate);

        DB::transaction(function () use ($data, $aov, $rate, $grand, $buybackRequest) {
            $buybackRequest->update([
                'mitra_id' => $data['mitra_id'],
                'aov' => $aov,
                'tingkat_penyusutan' => $rate,
                'grand_nilai_beli' => $grand['nilai_beli'],
                'grand_nilai_penyusutan' => $grand['nilai_penyusutan'],
                'grand_nilai_buyback' => $grand['nilai_buyback'],
            ]);

            $buybackRequest->items()->delete();
            $this->syncItems($buybackRequest, $data['items'], $rate);
        });

        return redirect()->route('buyback.index')->with('status', 'Pengajuan Buy Back berhasil diperbarui.');
    }

    public function destroy(Request $request, BuybackRequest $buybackRequest): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasAdminAccess() && $buybackRequest->created_by !== $user->id) {
            throw new HttpException(403, 'Kamu tidak punya akses ke pengajuan ini.');
        }

        if ($buybackRequest->isApproved() && ! $user->hasAdminAccess()) {
            throw new HttpException(403, 'Pengajuan yang sudah Approved tidak bisa dihapus.');
        }

        $buybackRequest->delete();

        return redirect()->route('buyback.index')->with('status', 'Pengajuan Buy Back berhasil dihapus.');
    }

    /**
     * Dua tahap approval berurutan: Head of SRN harus approve dulu, baru
     * Finance bisa approve — status baru jadi "approved" setelah
     * keduanya selesai. Admin bisa menyelesaikan kedua tahap sekaligus
     * (override), konsisten dengan admin selalu punya otoritas penuh di
     * tempat lain di aplikasi ini.
     */
    public function approve(Request $request, BuybackRequest $buybackRequest): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->canActAsHead() && ! $user->isFinance()) {
            throw new HttpException(403, 'Kamu tidak punya akses untuk approve pengajuan ini.');
        }

        if ($buybackRequest->isApproved()) {
            return back()->withErrors(['status' => 'Pengajuan ini sudah Approved.']);
        }

        if ($user->isFinance() && ! $buybackRequest->isHeadApproved()) {
            return back()->withErrors(['status' => 'Pengajuan ini harus di-approve Head of SRN dulu sebelum Finance bisa approve.']);
        }

        if ($user->canActAsHead() && $buybackRequest->isHeadApproved()) {
            return back()->withErrors(['status' => 'Pengajuan ini sudah di-approve Head of SRN.']);
        }

        if ($user->isFinance() && $buybackRequest->isFinanceApproved()) {
            return back()->withErrors(['status' => 'Pengajuan ini sudah di-approve Finance.']);
        }

        $update = [];

        if ($user->isAdmin() || $user->canActAsHead()) {
            $update['approved_by_head_id'] = $buybackRequest->approved_by_head_id ?? $user->id;
            $update['approved_by_head_at'] = $buybackRequest->approved_by_head_at ?? now();
        }

        if ($user->isAdmin() || $user->isFinance()) {
            $update['approved_by_finance_id'] = $buybackRequest->approved_by_finance_id ?? $user->id;
            $update['approved_by_finance_at'] = $buybackRequest->approved_by_finance_at ?? now();
        }

        $headDone = $update['approved_by_head_id'] ?? $buybackRequest->approved_by_head_id;
        $financeDone = $update['approved_by_finance_id'] ?? $buybackRequest->approved_by_finance_id;

        if ($headDone && $financeDone) {
            $update['status'] = 'approved';
            $update['approved_by'] = $update['approved_by_finance_id'] ?? $buybackRequest->approved_by_finance_id;
            $update['approved_at'] = $update['approved_by_finance_at'] ?? $buybackRequest->approved_by_finance_at;
        }

        $buybackRequest->update($update);

        return redirect()->route('buyback.index')->with('status', 'Pengajuan berhasil di-approve.');
    }

    private function authorizeEditable(Request $request, BuybackRequest $buybackRequest): void
    {
        $user = $request->user();

        if (! $user->hasAdminAccess() && $buybackRequest->created_by !== $user->id) {
            throw new HttpException(403, 'Kamu tidak punya akses ke pengajuan ini.');
        }

        if ($buybackRequest->isApproved()) {
            throw new HttpException(403, 'Pengajuan yang sudah Approved tidak bisa diubah.');
        }
    }

    private function mitraOptions($user)
    {
        $mitraList = Mitra::query()
            ->when($user->role === 'kae', fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra']);

        $aovByMitra = MitraHealthService::bulkForYtd($mitraList->pluck('id')->all());

        return $mitraList->map(function ($m) use ($aovByMitra) {
            $m->aov = $aovByMitra->get($m->id)['avg_order'] ?? 0;

            return $m;
        });
    }

    private function produkOptions()
    {
        return Produk::query()
            ->where('status', 'aktif')
            ->orderBy('brand')->orderBy('nama')
            ->get(['id', 'kode_sku', 'nama', 'brand', 'harga']);
    }

    private function resolveAov(int $mitraId): float
    {
        return MitraHealthService::bulkForYtd([$mitraId])->get($mitraId)['avg_order'] ?? 0;
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['nullable', 'exists:produk,id'],
            'items.*.nama_produk' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
            'items.*.tanggal_ed' => ['required', 'date'],
            'items.*.umur_produk_bulan' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function nilaiBuyback(float $subtotal, float $tingkatPenyusutanPersen, int $umurBulan): float
    {
        $rate = $tingkatPenyusutanPersen / 100;

        return round($subtotal * (1 - $rate) ** $umurBulan, 2);
    }

    /**
     * @return array{nilai_beli: float, nilai_penyusutan: float, nilai_buyback: float}
     */
    private function calcGrandNilai(array $items, float $tingkatPenyusutanPersen): array
    {
        $nilaiBeli = 0.0;
        $nilaiBuyback = 0.0;

        foreach ($items as $item) {
            $subtotal = $item['qty'] * $item['harga'];
            $nilaiBeli += $subtotal;
            $nilaiBuyback += $this->nilaiBuyback($subtotal, $tingkatPenyusutanPersen, $item['umur_produk_bulan']);
        }

        return [
            'nilai_beli' => $nilaiBeli,
            'nilai_penyusutan' => $nilaiBeli - $nilaiBuyback,
            'nilai_buyback' => $nilaiBuyback,
        ];
    }

    private function syncItems(BuybackRequest $buybackRequest, array $items, float $tingkatPenyusutanPersen): void
    {
        foreach ($items as $item) {
            $subtotal = $item['qty'] * $item['harga'];

            $buybackRequest->items()->create([
                'produk_id' => $item['produk_id'] ?? null,
                'nama_produk' => $item['nama_produk'],
                'qty' => $item['qty'],
                'harga' => $item['harga'],
                'subtotal' => $subtotal,
                'tanggal_ed' => $item['tanggal_ed'],
                'umur_produk_bulan' => $item['umur_produk_bulan'],
                'nilai_buyback' => $this->nilaiBuyback($subtotal, $tingkatPenyusutanPersen, $item['umur_produk_bulan']),
            ]);
        }
    }
}
