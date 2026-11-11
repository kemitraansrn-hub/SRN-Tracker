{{--
    Funnel (pipeline) Lead > Prospect > Closing per channel, buat
    membandingkan channel mana yang paling efisien (bukan cuma paling
    besar volumenya). Setiap bar dibandingkan terhadap Lead channel itu
    sendiri (Lead = 100%), jadi bentuknya langsung kebaca: channel yang
    bar-nya gak menyempit banyak = konversinya bagus.
    Expects: $rows = Collection seperti hasil DashboardController::akuisisiChannelPerformanceData()['rows']
             (tiap elemen: name, lead, prospect, closing, konv_lead, konv_prospect, biaya, cac, investasi_mitra)
             $rp = formatter Rupiah (sudah ada di scope dashboard.blade.php, diwarisi @include)
--}}
@php
    $pct = fn (int $bagian, int $dari) => $dari > 0 ? round($bagian / $dari * 100, 1) : 0;

    // "Terbaik" = ranking gabungan konversi (konv_lead, makin tinggi makin
    // bagus) DAN CAC (makin rendah makin bagus) -- bukan cuma salah satu.
    // Cuma channel yang closing-nya > 0 (CAC-nya kehitung) yang ikut
    // dipertimbangkan; channel lain otomatis gak eligible.
    $eligible = $rows->filter(fn ($r) => $r['lead'] > 0 && $r['closing'] > 0 && $r['cac'] !== null);
    $rankKonv = $eligible->sortByDesc('konv_lead')->values()->map(fn ($r, $i) => ['name' => $r['name'], 'rank' => $i])->pluck('rank', 'name');
    $rankCac = $eligible->sortBy('cac')->values()->map(fn ($r, $i) => ['name' => $r['name'], 'rank' => $i])->pluck('rank', 'name');
    $terbaikNama = $eligible->map(fn ($r) => ['name' => $r['name'], 'score' => $rankKonv[$r['name']] + $rankCac[$r['name']], 'konv' => $r['konv_lead']])
        ->sort(fn ($a, $b) => $a['score'] <=> $b['score'] ?: $b['konv'] <=> $a['konv'])
        ->first()['name'] ?? null;
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
                    <span style="width:70px; text-align:right; font-size:11px; color:var(--ink-muted); flex:none;">
                        <span class="tnum" style="font-weight:600;">{{ $lead }}</span>
                        <span class="tnum">&middot; 100%</span>
                    </span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; display:flex; justify-content:center;">
                        <div style="width:{{ max($prospectPct, $row['prospect'] > 0 ? 4 : 0) }}%; height:20px; border-radius:5px; background:var(--accent); transition:width .2s;"></div>
                    </div>
                    <span style="width:70px; text-align:right; font-size:11px; color:var(--ink-muted); flex:none;">
                        <span class="tnum" style="font-weight:600;">{{ (int) $row['prospect'] }}</span>
                        <span class="tnum">&middot; {{ number_format($prospectPct, 1, ',', '.') }}%</span>
                    </span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; display:flex; justify-content:center;">
                        <div style="width:{{ max($closingPct, $row['closing'] > 0 ? 4 : 0) }}%; height:20px; border-radius:5px; background:var(--good); transition:width .2s;"></div>
                    </div>
                    <span style="width:70px; text-align:right; font-size:11px; color:var(--ink-muted); flex:none;">
                        <span class="tnum" style="font-weight:600;">{{ (int) $row['closing'] }}</span>
                        <span class="tnum">&middot; {{ number_format($closingPct, 1, ',', '.') }}%</span>
                    </span>
                </div>
            </div>

            <div style="margin-top:10px; padding-top:10px; border-top:1px solid var(--line); display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--ink-muted);">
                <div style="display:flex; justify-content:space-between;">
                    <span>Konv. by Lead</span>
                    <span class="tnum" style="font-weight:700; color:var(--ink);">{{ $row['konv_lead'] === null ? '—' : number_format($row['konv_lead'], 1, ',', '.').'%' }}</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>CAC</span>
                    <span class="tnum" style="font-weight:700; color:var(--ink);">{{ $row['cac'] === null ? '—' : $rp($row['cac']) }}</span>
                </div>
            </div>
        </div>
    @empty
        <div style="color:var(--ink-muted); padding:12px;">Belum ada channel aktif.</div>
    @endforelse
</div>
