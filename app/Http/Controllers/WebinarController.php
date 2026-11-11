<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Tracking Webinar" (Development > Pareto & RTP): log tiap sesi webinar
 * (Pareto/Reguler/Dr. Shindy) + rekap jumlah peserta per bulan, mirip gaya
 * "Run Rate Mitra Active" di Dashboard (lihat RunRateService) tapi jauh
 * lebih sederhana -- cuma 3 baris nama webinar yang tetap, tanpa Avg
 * Daily/% Growth.
 */
class WebinarController extends Controller
{
    public function index(Request $request): View
    {
        $tahun = (int) $request->input('tahun', now()->year);

        $tahunOptions = Webinar::selectRaw('YEAR(tanggal_webinar) as tahun')->distinct()->pluck('tahun')->sortDesc()->values();
        if (! $tahunOptions->contains(now()->year)) {
            $tahunOptions->prepend(now()->year);
        }

        $items = Webinar::with('creator')->whereYear('tanggal_webinar', $tahun)
            ->orderByDesc('tanggal_webinar')->orderByDesc('id')->get();

        $lastMonth = $tahun === now()->year ? now()->month : 12;
        $months = range(1, $lastMonth);
        $monthLabels = collect($months)->mapWithKeys(fn ($m) => [$m => Carbon::create($tahun, $m, 1)->translatedFormat('F')]);

        $rows = collect(Webinar::NAMA_OPTIONS)->map(function (string $nama) use ($items, $months) {
            $entries = $items->where('nama_webinar', $nama);

            return [
                'nama' => $nama,
                'counts' => collect($months)->mapWithKeys(fn ($m) => [
                    $m => $entries->filter(fn (Webinar $w) => $w->tanggal_webinar->month === $m)->sum('jumlah_peserta'),
                ]),
            ];
        });

        $totalPerMonth = collect($months)->mapWithKeys(fn ($m) => [$m => $rows->sum(fn (array $r) => $r['counts'][$m])]);

        // Growth % vs bulan sebelumnya -- bulan kalender asli (bisa lintas
        // tahun buat Januari, dibandingkan ke Desember tahun sebelumnya),
        // bukan dibatasi ke bulan-bulan yang ditampilkan di tabel tahun ini.
        $prevMonthTotal = function (int $tahun, int $bulan): int {
            $prevBulan = $bulan - 1;
            $prevTahun = $tahun;
            if ($prevBulan < 1) {
                $prevBulan = 12;
                $prevTahun--;
            }

            return (int) Webinar::whereYear('tanggal_webinar', $prevTahun)->whereMonth('tanggal_webinar', $prevBulan)->sum('jumlah_peserta');
        };

        $growthPerMonth = collect($months)->mapWithKeys(function ($m) use ($tahun, $totalPerMonth, $prevMonthTotal) {
            $prev = $prevMonthTotal($tahun, $m);

            return [$m => $prev > 0 ? round(($totalPerMonth[$m] - $prev) / $prev * 100, 1) : null];
        });

        return view('webinar.index', [
            'items' => $items,
            'rows' => $rows,
            'months' => $months,
            'monthLabels' => $monthLabels,
            'totalPerMonth' => $totalPerMonth,
            'growthPerMonth' => $growthPerMonth,
            'tahun' => $tahun,
            'tahunOptions' => $tahunOptions,
            'namaOptions' => Webinar::NAMA_OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Webinar::create([...$data, 'created_by' => $request->user()->id]);

        return redirect()->route('tracking-webinar.index', ['tahun' => Carbon::parse($data['tanggal_webinar'])->year])
            ->with('status', 'Data webinar berhasil disimpan.');
    }

    public function edit(Webinar $webinar): View
    {
        return view('webinar.edit', ['webinar' => $webinar, 'namaOptions' => Webinar::NAMA_OPTIONS]);
    }

    public function update(Request $request, Webinar $webinar): RedirectResponse
    {
        $data = $this->validated($request);

        $webinar->update($data);

        return redirect()->route('tracking-webinar.index', ['tahun' => Carbon::parse($data['tanggal_webinar'])->year])
            ->with('status', 'Data webinar berhasil diperbarui.');
    }

    public function destroy(Webinar $webinar): RedirectResponse
    {
        $tahun = $webinar->tanggal_webinar->year;
        $webinar->delete();

        return redirect()->route('tracking-webinar.index', ['tahun' => $tahun])->with('status', 'Data webinar berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama_webinar' => ['required', Rule::in(Webinar::NAMA_OPTIONS)],
            'tanggal_webinar' => ['required', 'date'],
            'jumlah_peserta' => ['required', 'integer', 'min:0'],
            'bukti_zoom_url' => ['required', 'url', 'max:500'],
        ]);
    }
}
