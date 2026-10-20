<?php

namespace App\Http\Controllers;

use App\Models\AkuisisiReferral;
use App\Models\AkuisisiReferralTahap;
use App\Models\Mitra;
use App\Models\Pengakuisisi;
use App\Services\InternalMemoDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Akuisisi > Tracking Refferal" — implementasi Program Akuisisi Mitra
 * Offline (dokumen 2026-10-01). Lihat App\Models\AkuisisiReferral buat
 * logika hitung tahap/bonus.
 */
class AkuisisiReferralController extends Controller
{
    public function index(Request $request): View
    {
        $referrals = $this->filtered($request)->get();

        return view('akuisisi.tracking-referral', [
            'referrals' => $referrals,
            // 2026-10-19: Pengakuisisi sekarang master data sendiri (tabel
            // pengakuisisis), diambil dari database -- bukan lagi datalist
            // dari nama_pengakuisisi yang pernah dipakai. Menu ini kebuka
            // semua role KECUALI kae (permintaan eksplisit 2026-10-16) --
            // jadi dicek role!=='kae', bukan canViewAll() (yang bakal
            // ngecualiin growth_specialist juga).
            'pengakuisisiOptions' => $request->user()->role !== 'kae'
                ? Pengakuisisi::orderBy('nama')->get()
                : collect(),
            // mitraOptions cuma dipakai form Tambah Mitra (mutasi, dibatasi
            // admin/head/manager/supervisor -- route akuisisi-referral.*
            // BUKAN bagian dari grup menu Admin sidebar, jadi middleware
            // role:admin di sini otomatis juga meloloskan Supervisor, lihat
            // EnsureUserHasRole -- makanya pakai hasAdminAccess(), bukan
            // canAccessAdminGroup() yang secara sengaja ngecualiin Supervisor).
            'mitraOptions' => $request->user()->hasAdminAccess()
                ? Mitra::where('status', 'aktif')->whereDoesntHave('akuisisiReferral')->orderBy('nama')->get()
                : collect(),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $referrals = $this->filtered($request)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tracking Referral');

        $headers = ['Mitra', 'Kode Mitra', 'Pengakuisisi', 'Tanggal Akuisisi'];
        foreach ([1, 2, 3] as $t) {
            $headers = array_merge($headers, ["Transaksi {$t} - Bulan", "Transaksi {$t} - Nominal", "Transaksi {$t} - Bonus", "Transaksi {$t} - Status"]);
        }
        $headers[] = 'Total Bonus Berhak';
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)).'1')->getFont()->setBold(true);

        $r = 2;
        foreach ($referrals as $ref) {
            $tahaps = $ref->hitungTahap()->keyBy('tahap');
            $row = [
                $ref->mitra->nama,
                $ref->mitra->kode_mitra,
                $ref->nama_pengakuisisi,
                $ref->tanggal_akuisisi->format('d/m/Y'),
            ];
            foreach ([1, 2, 3] as $t) {
                $tahap = $tahaps->get($t);
                $row[] = $tahap?->bulan_label ?? '—';
                $row[] = $tahap?->nominal ?? 0;
                $row[] = $tahap?->nominal_bonus ?? 0;
                $row[] = $tahap ? $this->statusLabel($tahap->status) : 'Belum Ada Transaksi';
            }
            $row[] = $ref->totalBonusBerhak();
            $sheet->fromArray($row, null, 'A'.$r, true);
            $r++;
        }

        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'tracking_referral_'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'sudah_bayar' => 'Sudah Dibayar',
            'dibatalkan' => 'Dibatalkan',
            'di_luar_window' => 'Di Luar Window (Tidak Berlaku)',
            default => 'Belum Dibayar',
        };
    }

    private function filtered(Request $request)
    {
        $user = $request->user();

        return AkuisisiReferral::query()
            ->with(['mitra', 'tahaps'])
            // Semua role bisa buka menu ini KECUALI kae (permintaan eksplisit
            // 2026-10-16) -- gak dicek canViewAll() biar growth_specialist
            // ikut kebagian lihat juga.
            ->when($user->role === 'kae', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->filled('pengakuisisi_id'), fn ($q) => $q->where('pengakuisisi_id', $request->input('pengakuisisi_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $needle = $request->input('q');
                $q->whereHas('mitra', fn ($m) => $m->where('nama', 'like', "%{$needle}%")->orWhere('kode_mitra', 'like', "%{$needle}%"));
            })
            ->join('mitra', 'mitra.id', '=', 'akuisisi_referrals.mitra_id')
            ->orderBy('mitra.nama')
            ->select('akuisisi_referrals.*');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id', 'unique:akuisisi_referrals,mitra_id'],
            'pengakuisisi_id' => ['required', 'exists:pengakuisisis,id'],
            'tanggal_akuisisi' => ['required', 'date', 'before_or_equal:today'],
        ]);

        AkuisisiReferral::create([
            ...$data,
            // Disalin dari master biar tampilan/export gak perlu join --
            // lihat catatan di model AkuisisiReferral.
            'nama_pengakuisisi' => Pengakuisisi::find($data['pengakuisisi_id'])->nama,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('akuisisi-referral.index')->with('status', 'Mitra berhasil dikaitkan ke Pengakuisisi.');
    }

    public function destroy(AkuisisiReferral $akuisisiReferral): RedirectResponse
    {
        $nama = $akuisisiReferral->mitra->nama ?? 'mitra ini';
        $akuisisiReferral->delete();

        return redirect()->route('akuisisi-referral.index')->with('status', 'Tracking akuisisi untuk '.$nama.' dihapus.');
    }

    public function printIm(Request $request, AkuisisiReferral $akuisisiReferral): BinaryFileResponse|RedirectResponse
    {
        $data = $request->validate([
            'tahap' => ['required', 'integer', 'in:1,2,3'],
            'no_memo' => ['required', 'string', 'max:100'],
        ]);

        $tahap = $akuisisiReferral->hitungTahap()->firstWhere('tahap', (int) $data['tahap']);

        if (! $tahap || ! $tahap->dalam_window) {
            return back()->with('status', 'Transaksi ini belum ada datanya atau sudah di luar window, Internal Memo gak bisa dicetak.');
        }

        $phpWord = InternalMemoDocumentService::build($akuisisiReferral, $tahap, $data['no_memo']);
        $fileName = InternalMemoDocumentService::fileName($akuisisiReferral, $tahap);

        $tempFile = tempnam(sys_get_temp_dir(), 'im').'.docx';
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function updateTahap(Request $request, AkuisisiReferral $akuisisiReferral): RedirectResponse
    {
        $data = $request->validate([
            'tahap' => ['required', 'integer', 'in:1,2,3'],
            'status' => ['required', 'in:sudah_bayar,dibatalkan,belum_bayar'],
            'konfirmasi_tanpa_retur' => ['required_if:status,sudah_bayar', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $tahapLive = $akuisisiReferral->hitungTahap()->firstWhere('tahap', (int) $data['tahap']);

        if (! $tahapLive) {
            return back()->with('status', 'Tahap ini belum punya data transaksi, gak bisa diubah statusnya.');
        }

        if (! $tahapLive->dalam_window) {
            return back()->with('status', 'Tahap ini sudah di luar window 6 bulan — bonus tidak berlaku sesuai ketentuan program.');
        }

        if ($data['status'] === 'sudah_bayar' && ! $request->boolean('konfirmasi_tanpa_retur')) {
            return back()->withErrors(['konfirmasi_tanpa_retur' => 'Wajib dikonfirmasi: transaksi tervalidasi dan tidak ada retur/pembatalan.'])->withInput();
        }

        AkuisisiReferralTahap::updateOrCreate(
            ['akuisisi_referral_id' => $akuisisiReferral->id, 'tahap' => $data['tahap']],
            [
                'status' => $data['status'],
                'konfirmasi_tanpa_retur' => $data['status'] === 'sudah_bayar' ? true : $request->boolean('konfirmasi_tanpa_retur'),
                'catatan' => $data['catatan'] ?? null,
                'paid_by' => $data['status'] === 'sudah_bayar' ? $request->user()->id : null,
                'paid_at' => $data['status'] === 'sudah_bayar' ? now() : null,
            ]
        );

        $label = match ($data['status']) {
            'sudah_bayar' => 'ditandai Sudah Dibayar',
            'dibatalkan' => 'dibatalkan',
            default => 'dikembalikan ke Belum Dibayar',
        };

        return redirect()->route('akuisisi-referral.index')->with('status', 'Transaksi '.$data['tahap'].' berhasil '.$label.'.');
    }
}
