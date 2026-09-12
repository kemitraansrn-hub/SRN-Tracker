<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\PriceAdjustmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Price Adjustment Monitoring" — izin resmi dari Head buat mitra
 * menurunkan harga di marketplace, periode tanggal-mulai s/d tanggal-
 * selesai. Kalau lagi Approved & tanggalnya kena, Tracking CP otomatis
 * nolak dicatat kasus buat mitra itu (lihat
 * PriceAdjustmentRequest::adaIzinAktif(), dipanggil dari
 * CpCaseController::store()).
 */
class PriceAdjustmentRequestController extends Controller
{
    public const MARKETPLACE_OPTIONS = [
        'Shopee', 'Tokopedia', 'TikTok Shop', 'Lazada', 'Facebook Ads', 'Instagram Ads',
    ];

    public const STATUS_OPTIONS = ['Pending', 'Approved', 'Rejected'];

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = PriceAdjustmentRequest::with(['mitra', 'pengaju', 'penyetuju'])
            ->when(! $user->canViewAll(), fn ($q) => $q->where('diajukan_oleh', $user->id))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('toko', 'like', '%'.$request->input('q').'%')
                    ->orWhereHas('mitra', fn ($m) => $m->where('nama', 'like', '%'.$request->input('q').'%'));
            }))
            ->when($request->filled('status_approval'), fn ($q) => $q->where('status_approval', $request->input('status_approval')))
            ->when($request->boolean('sudah_diputuskan'), fn ($q) => $q->whereIn('status_approval', ['Approved', 'Rejected']))
            ->latest('tanggal_mulai');

        return view('price-adjustment.index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'statusOptions' => self::STATUS_OPTIONS,
        ]);
    }

    public function create(): View
    {
        return view('price-adjustment.form', $this->formOptions());
    }

    /**
     * Halaman monitoring buat Compliance (dan role company-wide lain) —
     * murni buat pantau (gak ada aksi Ajukan/Edit/Approve/Hapus di sini,
     * itu semua tetap di halaman "Price Adjustment" punya Sales/KAE).
     * Sengaja gak di-scope ke pengajuan sendiri kayak index() — monitoring
     * ini emang buat lihat SEMUA data, makanya dikunci ke role yang
     * canViewAll() aja.
     */
    public function monitoring(Request $request): View
    {
        abort_unless($request->user()->canViewAll(), 403);

        // Matikan badge notifikasi keputusan buat Compliance begitu halaman
        // ini dibuka — sengaja dikunci ke isCompliance() spesifik (bukan
        // canViewAll() umum) soalnya badge-nya juga cuma buat Compliance,
        // jangan sampai kebuka sama Admin/Head terus notifnya ikut hilang
        // padahal Compliance-nya sendiri belum lihat.
        if ($request->user()->isCompliance()) {
            PriceAdjustmentRequest::whereIn('status_approval', ['Approved', 'Rejected'])
                ->whereNull('dilihat_compliance_at')
                ->update(['dilihat_compliance_at' => now()]);
        }

        $query = PriceAdjustmentRequest::with(['mitra', 'pengaju', 'penyetuju'])
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('toko', 'like', '%'.$request->input('q').'%')
                    ->orWhereHas('mitra', fn ($m) => $m->where('nama', 'like', '%'.$request->input('q').'%'));
            }))
            ->when($request->filled('status_approval'), fn ($q) => $q->where('status_approval', $request->input('status_approval')))
            ->when($request->boolean('sudah_diputuskan'), fn ($q) => $q->whereIn('status_approval', ['Approved', 'Rejected']))
            ->latest('tanggal_mulai');

        return view('price-adjustment.monitoring', [
            'requests' => $query->paginate(20)->withQueryString(),
            'statusOptions' => self::STATUS_OPTIONS,
            'pendingCount' => PriceAdjustmentRequest::where('status_approval', 'Pending')->count(),
            'approvedCount' => PriceAdjustmentRequest::where('status_approval', 'Approved')->count(),
            'rejectedCount' => PriceAdjustmentRequest::where('status_approval', 'Rejected')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        PriceAdjustmentRequest::create([
            ...$data,
            'status_approval' => 'Pending',
            'diajukan_oleh' => $request->user()->id,
        ]);

        return redirect()->route('price-adjustment.index')->with('status', 'Pengajuan penyesuaian harga berhasil dicatat.');
    }

    public function edit(PriceAdjustmentRequest $priceAdjustmentRequest): View
    {
        abort_unless($priceAdjustmentRequest->status_approval === 'Pending', 403, 'Pengajuan yang sudah diputuskan tidak bisa diubah.');

        return view('price-adjustment.form', [...$this->formOptions(), 'priceAdjustmentRequest' => $priceAdjustmentRequest]);
    }

    public function update(Request $request, PriceAdjustmentRequest $priceAdjustmentRequest): RedirectResponse
    {
        abort_unless($priceAdjustmentRequest->status_approval === 'Pending', 403, 'Pengajuan yang sudah diputuskan tidak bisa diubah.');

        $priceAdjustmentRequest->update($this->validated($request));

        return redirect()->route('price-adjustment.index')->with('status', 'Pengajuan berhasil diperbarui.');
    }

    public function destroy(PriceAdjustmentRequest $priceAdjustmentRequest): RedirectResponse
    {
        $priceAdjustmentRequest->delete();

        return redirect()->route('price-adjustment.index')->with('status', 'Pengajuan berhasil dihapus.');
    }

    /**
     * Keputusan Approve/Reject dikunci ketat: cuma Head of SRN atau Manager
     * — Admin dan Supervisor sengaja TIDAK termasuk.
     */
    public function decide(Request $request, PriceAdjustmentRequest $priceAdjustmentRequest): RedirectResponse
    {
        abort_unless($request->user()->isHeadOrManager(), 403);

        $data = $request->validate([
            'keputusan' => ['required', 'string', 'in:Approved,Rejected'],
        ]);

        $priceAdjustmentRequest->update([
            'status_approval' => $data['keputusan'],
            'disetujui_oleh' => $request->user()->id,
        ]);

        return redirect()->route('price-adjustment.index')->with(
            'status',
            $data['keputusan'] === 'Approved' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.'
        );
    }

    private function formOptions(): array
    {
        return [
            'mitraOptions' => Mitra::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama', 'kode_mitra']),
            'marketplaceOptions' => self::MARKETPLACE_OPTIONS,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'toko' => ['required', 'string', 'max:255'],
            'marketplace' => ['required', 'string', 'in:'.implode(',', self::MARKETPLACE_OPTIONS)],
            'link_toko' => ['nullable', 'url', 'max:500'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'catatan' => ['nullable', 'string'],
        ]);

        return $data;
    }
}
