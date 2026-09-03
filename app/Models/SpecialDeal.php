<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'mitra_id', 'kae_user_id', 'deskripsi', 'status', 'kuartal', 'tahun',
    'segmen', 'target_kuartal', 'budget_persen', 'subsidi',
])]
class SpecialDeal extends Model
{
    public const SEGMEN_OPTIONS = ['PARETO', 'RTP', 'REGULER', 'SPECIAL REGULER'];

    public const SUBSIDI_OPTIONS = ['Iklan', 'Voucher Belanja'];

    public function periodeLabel(): ?string
    {
        return $this->kuartal && $this->tahun ? 'Q'.$this->kuartal.' '.$this->tahun : null;
    }

    public function targetMonthly(): ?float
    {
        return $this->target_kuartal ? round((float) $this->target_kuartal / 3, 2) : null;
    }

    public function nominalReward(): ?float
    {
        if (! $this->target_kuartal || $this->budget_persen === null) {
            return null;
        }

        return round((float) $this->target_kuartal * (float) $this->budget_persen / 100, 2);
    }

    /**
     * Nama bulan (dalam tahun berjalan kuartal ini) urut dari bulan pertama kuartal.
     *
     * @return int[]
     */
    public function bulanDalamKuartal(): array
    {
        if (! $this->kuartal) {
            return [];
        }

        $bulanAwal = ($this->kuartal - 1) * 3 + 1;

        return [$bulanAwal, $bulanAwal + 1, $bulanAwal + 2];
    }

    public function periodeRange(): ?array
    {
        if (! $this->kuartal || ! $this->tahun) {
            return null;
        }

        $bulanAwal = ($this->kuartal - 1) * 3 + 1;
        $mulai = \Carbon\Carbon::create($this->tahun, $bulanAwal, 1)->startOfDay();
        $selesai = $mulai->copy()->addMonths(2)->endOfMonth();

        return [$mulai, $selesai];
    }

    /**
     * Lampirkan pencapaian aktual per bulan (dari data order) ke tiap deal:
     * bulan_aktual (koleksi bulan => omset), q_sd (total kuartal), ach_pct, gap.
     *
     * @param  Collection<int, self>  $deals
     * @return Collection<int, self>
     */
    public static function attachAktual(Collection $deals): Collection
    {
        $sumsByGroup = [];
        foreach ($deals->groupBy(fn ($d) => $d->mitra_id.'-'.$d->tahun) as $key => $group) {
            [$mitraId, $tahun] = explode('-', $key, 2);

            $sumsByGroup[$key] = DB::table('orders')
                ->where('mitra_id', $mitraId)
                ->whereYear('tanggal_order', $tahun)
                ->selectRaw('MONTH(tanggal_order) as bulan, COALESCE(SUM(total_transaksi), 0) as total')
                ->groupBy('bulan')
                ->pluck('total', 'bulan');
        }

        return $deals->each(function (self $deal) use ($sumsByGroup) {
            $rows = $sumsByGroup[$deal->mitra_id.'-'.$deal->tahun] ?? collect();

            $deal->bulan_aktual = collect($deal->bulanDalamKuartal())
                ->mapWithKeys(fn ($b) => [$b => (float) ($rows[$b] ?? 0)]);
            $deal->q_sd = $deal->bulan_aktual->sum();
            $deal->ach_pct = $deal->target_kuartal > 0 ? round($deal->q_sd / (float) $deal->target_kuartal * 100, 1) : null;
            $deal->gap = $deal->target_kuartal !== null ? $deal->q_sd - (float) $deal->target_kuartal : null;
        });
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function kae()
    {
        return $this->belongsTo(User::class, 'kae_user_id');
    }
}
