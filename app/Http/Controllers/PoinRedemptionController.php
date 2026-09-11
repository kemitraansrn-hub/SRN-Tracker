<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\PoinRedemption;
use App\Models\RewardCatalog;
use App\Models\User;
use App\Services\MitraPoinService;
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
 * "Penukaran Poin": KAE (untuk mitranya sendiri) atau admin (mitra manapun)
 * menukar poin mitra dengan reward dari katalog. Poin yang sudah "on-check"
 * MAUPUN "approved" sama-sama mengurangi saldo yang terlihat (lihat
 * MitraPoinService::saldo()), supaya poin tidak bisa dijanjikan dobel ke
 * dua pengajuan berbeda. approve() tetap re-cek saldo sebagai jaring
 * pengaman kalau data order berubah (mis. dikoreksi admin) di antara waktu
 * pengajuan dibuat dan di-approve.
 */
class PoinRedemptionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $redemptions = PoinRedemption::with(['mitra:id,nama,kode_mitra,kae_code', 'creator:id,name', 'approver:id,name'])
            ->when($user->role === 'kae', fn ($q) => $q->whereHas('mitra', fn ($qq) => $qq->where('kae_code', $user->kae_code)))
            ->latest()
            ->get();

        return view('poin-redemption.index', ['redemptions' => $redemptions]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $kaeNameMap = User::kaeNameMap();

        $redemptions = PoinRedemption::with(['mitra:id,nama,kode_mitra,kae_code', 'approver:id,name'])
            ->when($user->role === 'kae', fn ($q) => $q->whereHas('mitra', fn ($qq) => $qq->where('kae_code', $user->kae_code)))
            ->latest()
            ->get();

        $headers = ['KAE', 'Tanggal', 'Kode Mitra', 'Nama Mitra', 'Reward', 'Qty', 'Keterangan', 'Poin', 'Note', 'Status', 'Approved By'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Penukaran Poin');
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBE5EF');

        $r = 2;
        foreach ($redemptions as $red) {
            $sheet->fromArray([
                $kaeNameMap[$red->mitra->kae_code ?? ''] ?? ($red->mitra->kae_code ?? '—'),
                $red->created_at->format('d/m/Y'),
                $red->mitra->kode_mitra ?? '—',
                $red->mitra->nama ?? '—',
                $red->nama_reward,
                $red->qty,
                $red->keterangan === 'di-uangkan' ? 'Di Uangkan' : 'Sesuai dengan Reward',
                $red->poin_terpakai,
                $red->note,
                $red->isApproved() ? 'Approved' : 'On Check',
                $red->approver->name ?? '—',
            ], null, 'A'.$r, true);
            $r++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'penukaran_poin_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function create(Request $request): View
    {
        return view('poin-redemption.form', [
            'redemption' => null,
            'mitraList' => $this->mitraOptions($request->user()),
            'rewardList' => RewardCatalog::where('status', 'aktif')->where('tahun', now()->year)->orderBy('poin_dibutuhkan')->get(),
            'tahun' => now()->year,
        ]);
    }

    /**
     * Satu pengajuan bisa berisi beberapa reward berbeda sekaligus (mitra
     * boleh tukar 2+ reward dalam satu transaksi selama total poinnya
     * cukup) — tiap baris reward tetap jadi satu row PoinRedemption
     * terpisah (skema tidak berubah), tapi divalidasi & disimpan sebagai
     * satu batch dalam satu transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tahun = now()->year;

        $data = $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'keterangan' => ['required', 'in:sesuai-reward,di-uangkan'],
            'rewards' => ['required', 'array', 'min:1'],
            'rewards.*.reward_catalog_id' => ['required', 'exists:reward_catalogs,id'],
            'rewards.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $this->assertMitraAllowed($user, $data['mitra_id']);

        $rewardIds = collect($data['rewards'])->pluck('reward_catalog_id')->unique();
        $rewards = RewardCatalog::whereIn('id', $rewardIds)->get()->keyBy('id');

        $totalPoin = collect($data['rewards'])->sum(fn ($line) => $rewards->get($line['reward_catalog_id'])->poin_dibutuhkan * $line['qty']);
        $this->assertSaldoCukup($data['mitra_id'], $tahun, $totalPoin);

        DB::transaction(function () use ($data, $rewards, $tahun, $user) {
            foreach ($data['rewards'] as $line) {
                $reward = $rewards->get($line['reward_catalog_id']);

                PoinRedemption::create([
                    'mitra_id' => $data['mitra_id'],
                    'tahun' => $tahun,
                    'reward_catalog_id' => $reward->id,
                    'nama_reward' => $reward->nama,
                    'keterangan' => $data['keterangan'],
                    'note' => $this->buildNote($reward, $data['keterangan']),
                    'poin_per_unit' => $reward->poin_dibutuhkan,
                    'qty' => $line['qty'],
                    'poin_terpakai' => $reward->poin_dibutuhkan * $line['qty'],
                    'status' => 'on-check',
                    'created_by' => $user->id,
                ]);
            }
        });

        $jumlahReward = count($data['rewards']);

        return redirect()->route('poin-redemption.index')->with('status', 'Penukaran poin berhasil diajukan ('.$jumlahReward.' reward).');
    }

    public function edit(Request $request, PoinRedemption $poinRedemption): View
    {
        $this->authorizeEditable($request, $poinRedemption);

        return view('poin-redemption.form', [
            'redemption' => $poinRedemption,
            'mitraList' => $this->mitraOptions($request->user(), excludeRedemptionId: $poinRedemption->id),
            'rewardList' => RewardCatalog::where('status', 'aktif')->where('tahun', $poinRedemption->tahun)->orderBy('poin_dibutuhkan')->get(),
            'tahun' => $poinRedemption->tahun,
        ]);
    }

    public function update(Request $request, PoinRedemption $poinRedemption): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeEditable($request, $poinRedemption);

        $data = $this->validateRequest($request);
        $this->assertMitraAllowed($user, $data['mitra_id']);
        $reward = RewardCatalog::findOrFail($data['reward_catalog_id']);
        $poinTerpakai = $reward->poin_dibutuhkan * $data['qty'];
        $this->assertSaldoCukup($data['mitra_id'], $poinRedemption->tahun, $poinTerpakai, excludeId: $poinRedemption->id);

        $poinRedemption->update([
            'mitra_id' => $data['mitra_id'],
            'reward_catalog_id' => $reward->id,
            'nama_reward' => $reward->nama,
            'keterangan' => $data['keterangan'],
            'note' => $this->buildNote($reward, $data['keterangan']),
            'poin_per_unit' => $reward->poin_dibutuhkan,
            'qty' => $data['qty'],
            'poin_terpakai' => $poinTerpakai,
        ]);

        return redirect()->route('poin-redemption.index')->with('status', 'Penukaran poin berhasil diperbarui.');
    }

    public function destroy(Request $request, PoinRedemption $poinRedemption): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasAdminAccess() && $poinRedemption->created_by !== $user->id) {
            throw new HttpException(403, 'Kamu tidak punya akses ke pengajuan ini.');
        }

        if ($poinRedemption->isApproved() && ! $user->hasAdminAccess()) {
            throw new HttpException(403, 'Pengajuan yang sudah Approved tidak bisa dihapus.');
        }

        $poinRedemption->delete();

        return redirect()->route('poin-redemption.index')->with('status', 'Penukaran poin berhasil dihapus.');
    }

    public function approve(Request $request, PoinRedemption $poinRedemption): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isHead()) {
            throw new HttpException(403, 'Kamu tidak punya akses untuk approve pengajuan ini.');
        }

        if ($poinRedemption->isApproved()) {
            return back()->withErrors(['status' => 'Pengajuan ini sudah Approved.']);
        }

        $earned = MitraPoinService::forMitra($poinRedemption->mitra_id, $poinRedemption->tahun)['total'];
        $usedByOthers = PoinRedemption::where('mitra_id', $poinRedemption->mitra_id)
            ->where('tahun', $poinRedemption->tahun)
            ->whereIn('status', ['on-check', 'approved'])
            ->where('id', '!=', $poinRedemption->id)
            ->sum('poin_terpakai');

        if ($poinRedemption->poin_terpakai > $earned - $usedByOthers) {
            return back()->withErrors(['status' => 'Poin mitra sudah tidak cukup untuk pengajuan ini (kemungkinan data order berubah sejak diajukan).']);
        }

        $poinRedemption->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('poin-redemption.index')->with('status', 'Penukaran poin berhasil di-approve.');
    }

    private function authorizeEditable(Request $request, PoinRedemption $poinRedemption): void
    {
        $user = $request->user();

        if (! $user->hasAdminAccess() && $poinRedemption->created_by !== $user->id) {
            throw new HttpException(403, 'Kamu tidak punya akses ke pengajuan ini.');
        }

        if ($poinRedemption->isApproved()) {
            throw new HttpException(403, 'Pengajuan yang sudah Approved tidak bisa diubah.');
        }
    }

    private function assertMitraAllowed($user, int $mitraId): void
    {
        if ($user->role !== 'kae') {
            return;
        }

        if (Mitra::where('id', $mitraId)->where('kae_code', $user->kae_code)->doesntExist()) {
            throw new HttpException(403, 'Mitra ini bukan mitra kamu.');
        }
    }

    private function assertSaldoCukup(int $mitraId, int $tahun, int $poinTerpakai, ?int $excludeId = null): void
    {
        $earned = MitraPoinService::forMitra($mitraId, $tahun)['total'];
        $used = PoinRedemption::where('mitra_id', $mitraId)
            ->where('tahun', $tahun)
            ->whereIn('status', ['on-check', 'approved'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('poin_terpakai');

        if ($poinTerpakai > $earned - $used) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'qty' => 'Poin mitra tidak cukup. Saldo tersedia: '.($earned - $used).' poin.',
            ]);
        }
    }

    private function mitraOptions($user, ?int $excludeRedemptionId = null)
    {
        $mitraList = Mitra::query()
            ->when($user->role === 'kae', fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra']);

        // Saat edit, tambahkan kembali poin_terpakai milik pengajuan yang
        // sedang diedit — supaya saldo yang ditampilkan mencerminkan ruang
        // yang benar-benar tersedia untuk pengajuan ini (konsisten dengan
        // assertSaldoCukup() yang juga mengecualikan pengajuan ini sendiri).
        $excluded = $excludeRedemptionId ? PoinRedemption::find($excludeRedemptionId) : null;

        $saldoByMitra = $mitraList->mapWithKeys(function ($m) use ($excluded) {
            $saldo = MitraPoinService::saldo($m->id, now()->year);
            if ($excluded && $excluded->mitra_id === $m->id) {
                $saldo += $excluded->poin_terpakai;
            }

            return [$m->id => $saldo];
        });

        return $mitraList->map(function ($m) use ($saldoByMitra) {
            $m->saldo_poin = $saldoByMitra->get($m->id, 0);

            return $m;
        });
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'reward_catalog_id' => ['required', 'exists:reward_catalogs,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'in:sesuai-reward,di-uangkan'],
        ]);
    }

    /**
     * "Di Uangkan" (reward barang dicairkan jadi cash) butuh nominal harga
     * & budget reward tercatat sebagai snapshot, karena reward_catalogs bisa
     * diedit/dihapus belakangan.
     */
    private function buildNote(RewardCatalog $reward, string $keterangan): ?string
    {
        if ($keterangan !== 'di-uangkan') {
            return null;
        }

        $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
        $note = 'Harga Reward: '.$rp($reward->harga_reward);

        if ($reward->budget_reward !== null) {
            $note .= ' • Budget Reward: '.$rp($reward->budget_reward);
        }

        return $note;
    }
}
