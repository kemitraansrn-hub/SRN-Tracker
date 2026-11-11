{{--
    Summary Pipeline -- funnel TOTAL (semua channel dijumlah) Lead > Prospect
    > Closing, gaya/komponen di-copy persis dari "Funnel Pipeline" milik
    Database Lead (resources/views/akuisisi/leads/index.blade.php): bar
    lebar = reached/base (base = Lead), label % di sampingnya = konversi
    dari TAHAP SEBELUMNYA (bukan dari base) -- beda definisi dari "Konv. by
    Lead" di kartu per-channel, sengaja disamakan ke pola aslinya biar
    kartu ini konsisten dgn versi Database Lead yang di-copy.
    Expects: $totals = $akuisisiChannelPerformance['totals']
             (lead, prospect, closing, konv_lead, konv_prospect, cac, biaya, investasi_mitra)
--}}
@php
    $steps = [
        ['name' => 'Lead', 'reached' => (int) $totals['lead'], 'is_closing' => false],
        ['name' => 'Prospect', 'reached' => (int) $totals['prospect'], 'is_closing' => false],
        ['name' => 'Closing', 'reached' => (int) $totals['closing'], 'is_closing' => true],
    ];
    $base = $steps[0]['reached'];
@endphp
<section class="card" style="display:flex; flex-direction:column; margin-bottom:20px;">
    <div class="card-head">
        <div class="card-title">Summary Pipeline</div>
        <div class="card-hint">Total semua channel &mdash; {{ $akuisisiPeriode['label'] }}</div>
    </div>

    @if ($base === 0)
        <div style="color:var(--ink-muted); font-size:13px; text-align:center; padding:16px;">Belum ada Lead tercatat di periode ini.</div>
    @else
        <div style="display:flex; flex-direction:column; gap:10px;">
            @foreach ($steps as $index => $step)
                @php
                    $width = $step['reached'] ? max($step['reached'] / $base * 100, 4) : 0;
                    $previous = $steps[$index - 1] ?? null;
                    $conversion = ($previous && $previous['reached'] > 0) ? $step['reached'] / $previous['reached'] : null;
                    $pct = $conversion === null ? null : number_format($conversion * 100, $conversion * 100 < 10 ? 1 : 0, ',', '.').'%';
                @endphp
                <div style="display:grid; grid-template-columns:90px 1fr 90px; align-items:center; gap:10px;"
                    title="{{ $previous && $pct ? 'Konversi dari '.$previous['name'].': '.$pct : '' }}">
                    <span style="font-size:12px; font-weight:600; color:var(--ink); white-space:nowrap;">
                        @if ($step['is_closing'])
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--good)" stroke-width="2.5" style="vertical-align:-2px; margin-right:3px;"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @endif
                        {{ $step['name'] }}
                    </span>
                    <span style="height:14px; background:var(--surface-alt); border-radius:5px; overflow:hidden;">
                        <span style="display:block; height:100%; width:{{ round($width, 2) }}%; background:{{ $step['is_closing'] ? 'var(--good)' : 'var(--accent)' }}; border-radius:5px;"></span>
                    </span>
                    <span style="text-align:right; font-size:12px; white-space:nowrap;">
                        <span class="tnum" style="font-weight:700;">{{ number_format($step['reached'], 0, ',', '.') }}</span>
                        @if ($index > 0 && $pct)
                            <span style="color:var(--ink-faint);">&middot; {{ $pct }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>

        <div style="margin-top:14px; padding-top:12px; border-top:1px solid var(--line); display:flex; gap:20px; flex-wrap:wrap; font-size:11.5px; color:var(--ink-muted);">
            <div style="display:flex; align-items:center; gap:6px;">Konv. Closing by Lead
                <span class="tnum" style="font-weight:700; color:var(--ink);">{{ $totals['konv_lead'] === null ? '—' : number_format($totals['konv_lead'], 1, ',', '.').'%' }}</span>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">Konv. Closing by Prospect
                <span class="tnum" style="font-weight:700; color:var(--ink);">{{ $totals['konv_prospect'] === null ? '—' : number_format($totals['konv_prospect'], 1, ',', '.').'%' }}</span>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">CAC
                <span class="tnum" style="font-weight:700; color:var(--ink);">{{ $totals['cac'] === null ? '—' : $rp($totals['cac']) }}</span>
            </div>
        </div>
    @endif
</section>
