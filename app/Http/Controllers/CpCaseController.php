<?php

namespace App\Http\Controllers;

use App\Models\CpCase;
use App\Models\CpTakedownBanding;
use App\Models\KotaKabupaten;
use App\Models\Mitra;
use App\Models\PriceAdjustmentRequest;
use App\Models\Produk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tracking CP" — log kasus pelanggaran cutting price yang ditemukan tim
 * Compliance di marketplace (Shopee/Tokopedia/dst), lengkap alur follow-up
 * 3 ronde sampai approval takedown. Begitu satu kasus disetujui takedown-nya
 * DAN mitra-nya banding, otomatis dibuatkan 1 baris CpTakedownBanding
 * (lihat updateStatus()) — bukan "pindah data", cuma nambah detail proses
 * bandingnya.
 */
class CpCaseController extends Controller
{
    public const PLATFORM_OPTIONS = [
        'Shopee', 'Tokopedia', 'TikTok Shop', 'Lazada', 'Facebook Ads', 'Instagram Ads',
    ];

    public const STATUS_KASUS_OPTIONS = [
        'Baru Ditemukan', 'Progres', 'Pengajuan Takedown', 'Case Closed',
    ];

    /**
     * Pipeline status_takedown setelah Compliance mengajukan Pengajuan
     * Takedown: Head of SRN approve/reject dulu, baru Compliance bisa list
     * ke marketplace, baru dikonfirmasi take down beneran.
     */
    public const STATUS_TAKEDOWN_OPTIONS = [
        'Menunggu Approval', 'Approved', 'Rejected', 'Listed ke Shopee', 'Take Down',
    ];

    public function index(Request $request): View
    {
        $query = CpCase::with(['mitra', 'kotaKabupaten', 'produk', 'takedownBanding'])
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('nama_toko', 'like', '%'.$request->input('q').'%')
                    ->orWhere('kode', 'like', '%'.$request->input('q').'%')
                    ->orWhere('nama_mitra_manual', 'like', '%'.$request->input('q').'%')
                    ->orWhereHas('mitra', fn ($m) => $m->where('nama', 'like', '%'.$request->input('q').'%'));
            }))
            ->when($request->filled('status_kasus'), fn ($q) => $q->where('status_kasus', $request->input('status_kasus')))
            ->when($request->boolean('menunggu_approval'), fn ($q) => $q->where('status_takedown', 'Menunggu Approval'))
            ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->input('platform')))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal_temuan', '>=', $request->input('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal_temuan', '<=', $request->input('sampai')))
            ->latest('tanggal_temuan');

        return view('cp-case.index', [
            'cases' => $query->paginate(20)->withQueryString(),
            'statusOptions' => self::STATUS_KASUS_OPTIONS,
            'platformOptions' => self::PLATFORM_OPTIONS,
        ]);
    }

    public function create(): View
    {
        return view('cp-case.form', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['mitra_id'] && PriceAdjustmentRequest::adaIzinAktif($data['mitra_id'], $data['tanggal_temuan'])) {
            return back()->withInput()->withErrors([
                'mitra_id' => 'Mitra ini punya izin Price Adjustment yang aktif & disetujui buat tanggal ini — penurunan harganya sah, jangan dicatat sebagai pelanggaran.',
            ]);
        }

        CpCase::create([
            ...$data,
            'kode' => $this->generateKode(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus baru berhasil dicatat.');
    }

    public function edit(CpCase $cpCase): View
    {
        return view('cp-case.form', [...$this->formOptions(), 'cpCase' => $cpCase]);
    }

    private function formOptions(): array
    {
        return [
            'mitraOptions' => Mitra::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama', 'kode_mitra']),
            'kotaOptions' => KotaKabupaten::orderBy('nama')->get(['id', 'nama', 'provinsi']),
            'produkOptions' => Produk::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama', 'brand', 'harga_het']),
            'platformOptions' => self::PLATFORM_OPTIONS,
        ];
    }

    public function update(Request $request, CpCase $cpCase): RedirectResponse
    {
        $data = $this->validated($request, $cpCase);

        $cpCase->update($data);

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus berhasil diperbarui.');
    }

    public function destroy(CpCase $cpCase): RedirectResponse
    {
        $cpCase->delete();

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus berhasil dihapus.');
    }

    /**
     * Progres kasus (Follow Up 1-3 -> keputusan Status Kasus) dipicu lewat
     * tombol dinamis di daftar Tracking CP, bukan dari halaman Edit — Edit
     * cuma buat koreksi data input awal. Tombolnya berubah tahap begitu
     * tahap sebelumnya sudah keisi: FU1 -> FU2 -> FU3 -> Status Kasus.
     */
    public function updateFollowUp(Request $request, CpCase $cpCase, int $round): RedirectResponse
    {
        abort_unless(in_array($round, [1, 2, 3], true), 404);

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'status' => ['nullable', 'boolean'],
        ]);

        $cpCase->update([
            "follow_up_{$round}_tanggal" => $data['tanggal'],
            "follow_up_{$round}_status" => $request->boolean('status'),
        ]);

        if ($cpCase->status_kasus === 'Baru Ditemukan') {
            $cpCase->update(['status_kasus' => 'Progres']);
        }

        return redirect()->route('tracking-cp.index')->with('status', "Follow Up {$round} berhasil disimpan.");
    }

    /**
     * Salah satu dari 3 keputusan Status Kasus (dipilih lewat pop-up setelah
     * Follow Up 3 selesai): mitra sudah naikkan harga -> tutup kasus.
     */
    public function updateCaseClose(Request $request, CpCase $cpCase): RedirectResponse
    {
        $data = $request->validate([
            'tanggal_case_close' => ['required', 'date'],
            'bukti_case_close' => ['nullable', 'url', 'max:500'],
        ]);

        $data['status_kasus'] = 'Case Closed';

        $cpCase->update($data);

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus berhasil ditutup.');
    }

    /**
     * Keputusan Status Kasus: masih dipantau, belum ditutup/takedown —
     * tombol "Status Kasus" tetap muncul di daftar buat diputuskan lagi
     * nanti.
     */
    public function markProgres(CpCase $cpCase): RedirectResponse
    {
        $cpCase->update(['status_kasus' => 'Progres']);

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus ditandai masih Progres.');
    }

    /**
     * Keputusan Status Kasus: ajukan takedown ke marketplace — cuma
     * mengajukan, belum ada keputusan. Head of SRN yang approve/reject lewat
     * decideTakedown().
     */
    public function updateTakedown(CpCase $cpCase): RedirectResponse
    {
        $cpCase->update([
            'status_kasus' => 'Pengajuan Takedown',
            'status_takedown' => 'Menunggu Approval',
        ]);

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus diajukan takedown, menunggu approval Head.');
    }

    /**
     * Cuma Head of SRN yang boleh approve/reject pengajuan takedown — bukan
     * Admin, sengaja dipisah biar approval-nya jelas siapa yang tanggung
     * jawab. Kalau ditolak, kasus balik ke Progres supaya Compliance bisa
     * ambil keputusan lain.
     */
    public function decideTakedown(Request $request, CpCase $cpCase): RedirectResponse
    {
        abort_unless($request->user()->isHead(), 403);

        $data = $request->validate([
            'keputusan' => ['required', 'string', 'in:Approved,Rejected'],
        ]);

        $cpCase->update([
            'status_takedown' => $data['keputusan'],
            'takedown_decided_by' => $request->user()->id,
            'takedown_decided_at' => now(),
            'status_kasus' => $data['keputusan'] === 'Rejected' ? 'Progres' : 'Pengajuan Takedown',
        ]);

        return redirect()->route('tracking-cp.index')->with('status', $data['keputusan'] === 'Approved' ? 'Pengajuan takedown disetujui.' : 'Pengajuan takedown ditolak, kasus balik ke Progres.');
    }

    /**
     * Compliance menandai udah dilist ke Shopee, setelah Head approve.
     */
    public function markListedToShopee(CpCase $cpCase): RedirectResponse
    {
        abort_unless($cpCase->status_takedown === 'Approved', 404);

        $cpCase->update(['status_takedown' => 'Listed ke Shopee']);

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus ditandai sudah dilist ke Shopee.');
    }

    /**
     * Keputusan Shopee atas listing takedown yang diajukan Compliance.
     * Kalau ditolak, sama seperti reject Head — balik ke Progres, bisa
     * diajukan Takedown lagi. Kalau disetujui, kasusnya resmi Take Down dan
     * masuk menu Take Down & Banding, otomatis dibikinkan 1 baris
     * CpTakedownBanding (idempotent) buat nyimpen detail proses bandingnya
     * nanti.
     */
    public function decideShopeeListing(Request $request, CpCase $cpCase): RedirectResponse
    {
        abort_unless($cpCase->status_takedown === 'Listed ke Shopee', 404);

        $data = $request->validate([
            'keputusan' => ['required', 'string', 'in:Approved,Rejected'],
        ]);

        if ($data['keputusan'] === 'Rejected') {
            $cpCase->update([
                'status_takedown' => 'Rejected',
                'status_kasus' => 'Progres',
            ]);

            return redirect()->route('tracking-cp.index')->with('status', 'Listing ditolak Shopee, kasus balik ke Progres.');
        }

        $cpCase->update(['status_takedown' => 'Take Down']);

        if (! $cpCase->takedownBanding) {
            CpTakedownBanding::create([
                'cp_case_id' => $cpCase->id,
                'tanggal_takedown' => now()->toDateString(),
                'jumlah_follow_up' => collect([1, 2, 3])->filter(fn ($n) => $cpCase->{"follow_up_{$n}_tanggal"})->count(),
                'keputusan_final' => CpTakedownBanding::KEPUTUSAN_FINAL_DEFAULT,
            ]);
        }

        return redirect()->route('tracking-cp.index')->with('status', 'Kasus ditandai Take Down, masuk ke menu Take Down & Banding.');
    }

    private function validated(Request $request, ?CpCase $cpCase = null): array
    {
        $data = $request->validate([
            'tanggal_temuan' => ['required', 'date'],
            'mitra_id' => ['nullable', 'exists:mitra,id'],
            'nama_mitra_manual' => ['nullable', 'string', 'max:255', 'required_without:mitra_id'],
            'nama_toko' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:'.implode(',', self::PLATFORM_OPTIONS)],
            'terjual' => ['nullable', 'integer', 'min:0'],
            'terlaris' => ['nullable', 'integer', 'min:0'],
            'kota_kabupaten_id' => ['nullable', 'exists:kota_kabupatens,id'],
            'link_etalase' => ['nullable', 'url', 'max:500'],
            'kode_barcode' => ['nullable', 'string', 'max:100'],
            'produk_id' => ['required', 'exists:produk,id'],
            'harga_sop' => ['required', 'numeric', 'min:0'],
            'harga_pelanggaran' => ['required', 'numeric', 'min:0'],
            'status_kasus' => ['required', 'string', 'in:'.implode(',', self::STATUS_KASUS_OPTIONS)],
            'bukti_temuan' => ['nullable', 'url', 'max:500'],
        ]);

        if (empty($data['mitra_id'])) {
            $data['mitra_id'] = null;
        } else {
            $data['nama_mitra_manual'] = null;
        }

        return $data;
    }

    private function generateKode(): string
    {
        $last = CpCase::where('kode', 'like', 'PC-%')->orderByDesc('id')->value('kode');
        $next = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'PC-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
