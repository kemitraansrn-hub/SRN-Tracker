<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\PriceAdjustmentRequest;
use App\Models\Produk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Price Adjustment" — izin resmi dari Head/Manager buat mitra
 * menurunkan harga SKU tertentu di marketplace, periode tanggal-mulai
 * s/d tanggal-selesai. 1 pengajuan = 1 mitra/toko, tapi bisa berisi
 * banyak SKU (masing-masing punya HET, harga diskon yang diajukan, dan
 * link etalase Shopee-nya sendiri) — lihat PriceAdjustmentItem.
 *
 * Sengaja TIDAK ada blokir otomatis ke Tracking CP: keputusan final soal
 * "ini pelanggaran atau bukan" tetap di tangan Compliance secara manual,
 * cuma dibantu visibility data dari halaman Price Adjustment Monitoring.
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

        $query = PriceAdjustmentRequest::with(['mitra', 'pengaju', 'penyetuju', 'items.produk'])
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

        $query = PriceAdjustmentRequest::with(['mitra', 'pengaju', 'penyetuju', 'items.produk'])
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
        [$data, $items] = $this->validated($request);

        DB::transaction(function () use ($data, $items, $request) {
            $priceAdjustmentRequest = PriceAdjustmentRequest::create([
                ...$data,
                'status_approval' => 'Pending',
                'diajukan_oleh' => $request->user()->id,
            ]);

            $priceAdjustmentRequest->items()->createMany($items);
        });

        return redirect()->route('price-adjustment.index')->with('status', 'Pengajuan penyesuaian harga berhasil dicatat.');
    }

    public function edit(PriceAdjustmentRequest $priceAdjustmentRequest): View
    {
        abort_unless($priceAdjustmentRequest->status_approval === 'Pending', 403, 'Pengajuan yang sudah diputuskan tidak bisa diubah.');

        return view('price-adjustment.form', [
            ...$this->formOptions(),
            'priceAdjustmentRequest' => $priceAdjustmentRequest->load('items'),
        ]);
    }

    public function update(Request $request, PriceAdjustmentRequest $priceAdjustmentRequest): RedirectResponse
    {
        abort_unless($priceAdjustmentRequest->status_approval === 'Pending', 403, 'Pengajuan yang sudah diputuskan tidak bisa diubah.');

        [$data, $items] = $this->validated($request);

        DB::transaction(function () use ($priceAdjustmentRequest, $data, $items) {
            $priceAdjustmentRequest->update($data);
            // Simpel: buang semua item lama, catat ulang dari yang disubmit —
            // gak perlu diff satu-satu soalnya jumlah SKU per pengajuan kecil.
            $priceAdjustmentRequest->items()->delete();
            $priceAdjustmentRequest->items()->createMany($items);
        });

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
            'produkOptions' => Produk::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama', 'brand', 'harga_het']),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'toko' => ['required', 'string', 'max:255'],
            'marketplace' => ['required', 'string', 'in:'.implode(',', self::MARKETPLACE_OPTIONS)],
            'link_toko' => ['nullable', 'url', 'max:500'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'exists:produk,id'],
            'items.*.harga_het' => ['required', 'numeric', 'min:0'],
            'items.*.harga_diskon' => ['required', 'numeric', 'min:0'],
            'items.*.link_etalase' => ['required', 'url', 'max:500'],
        ]);

        $items = array_values($validated['items']);
        unset($validated['items']);

        return [$validated, $items];
    }
}
