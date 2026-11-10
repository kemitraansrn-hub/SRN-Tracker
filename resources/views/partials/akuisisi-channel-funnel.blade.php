{{--
    Funnel (pipeline) Lead > Prospect > Closing per channel, buat
    membandingkan channel mana yang paling efisien (bukan cuma paling
    besar volumenya). Setiap bar dibandingkan terhadap Lead channel itu
    sendiri (Lead = 100%), jadi bentuknya langsung kebaca: channel yang
    bar-nya gak menyempit banyak = konversinya bagus.
    Expects: $rows = Collection seperti hasil DashboardController::akuisisiChannelPerformanceData()['rows']
             (tiap elemen: name, lead, prospect, closing, konv_lead, konv_prospect)
--}}
@php
    $terbaikNama = $rows->filter(fn ($r) => $r['lead'] > 0)->sortByDesc('konv_lead')->first()['name'] ?? null;
    $pct = fn (int $bagian, int $dari) => $dari > 0 ? round($bagian / $dari * 100, 1) : 0;
@endphp
<div style="display:flex; align-items:center; gap:16px; margin-bottom:16px; flex-wrap:wrap; font-size:11.5px; color:var(--ink-muted);">
    <span style="display:flex; align-items:center; gap:6px;"><span style="width:10px; height:10px; border-radius:3px; background:var(--ink-faint); display:inline-block;"></span>Lead</span>
    <span style="display:flex; align-items:center; gap:6px;"><span style="width:10px; height:10px; border-radius:3px; background:var(--accent); display:inline-block;"></span>Prospect</span>
    <span style="display:flex; align-items:center; gap:6px;"><span style="width:10px; height:10px; border-radius:3px; background:var(--good); display:inline-block;"></span>Closing</span>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
    @forelse ($rows as $row)
        @php
            $lead = (int) $row['lead'];
            $prospectPct = $pct((int) $row['prospect'], $lead);
            $closingPct = $pct((int) $row['closing'], $lead);
            $isBest = $terbaikNama !== null && $row['name'] === $terbaikNama;
        @endphp
        <div class="card" style="padding:14px 16px; {{ $isBest ? 'border-color:var(--good);' : '' }}">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px;">
                <span style="font-weight:700; font-size:13px;">{{ $row['name'] }}</span>
                @if ($isBest)
                    <span class="chip chip-good">&#9733; Terbaik</span>
                @endif
            </div>

            <div style="display:flex; flex-direction:column; gap:6px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; display:flex; justify-content:center;">
                        <div style="width:100%; height:20px; border-radius:5px; background:var(--ink-faint);"></div>
                    </div>
                    <span class="tnum" style="width:42px; text-align:right; font-size:11px; color:var(--ink-muted); flex:none;">{{ $lead }}</span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; display:flex; justify-content:center;">
                        <div style="width:{{ max($prospectPct, $row['prospect'] > 0 ? 4 : 0) }}%; height:20px; border-radius:5px; background:var(--accent); transition:width .2s;"></div>
                    </div>
                    <span class="tnum" style="width:42px; text-align:right; font-size:11px; color:var(--ink-muted); flex:none;">{{ (int) $row['prospect'] }}</span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; display:flex; justify-content:center;">
                        <div style="width:{{ max($closingPct, $row['closing'] > 0 ? 4 : 0) }}%; height:20px; border-radius:5px; background:var(--good); transition:width .2s;"></div>
                    </div>
                    <span class="tnum" style="width:42px; text-align:right; font-size:11px; color:var(--ink-muted); flex:none;">{{ (int) $row['closing'] }}</span>
                </div>
            </div>

            <div style="margin-top:10px; padding-top:10px; border-top:1px solid var(--line); display:flex; justify-content:space-between; font-size:11.5px; color:var(--ink-muted);">
                <span>Konv. by Lead</span>
                <span class="tnum" style="font-weight:700; color:var(--ink);">{{ $row['konv_lead'] === null ? '—' : number_format($row['konv_lead'], 1, ',', '.').'%' }}</span>
            </div>
        </div>
    @empty
        <div style="color:var(--ink-muted); padding:12px;">Belum ada channel aktif.</div>
    @endforelse
</div>
