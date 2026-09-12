@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $segTabAktif = request('tab') === 'portfolio' ? 'portfolio' : 'segmentasi';
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
        <div>
            <h1 class="display" style="font-size:24px;">Segmentasi Mitra &mdash; {{ $segmen }}</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $periodeLabel }} &middot; berdasarkan Target Bulanan yang sudah di-import
            </div>
        </div>
        <form method="GET" action="{{ route('segmentasi.show', $segmen) }}" class="field-row" style="align-items:flex-end;">
            <input type="hidden" name="tab" value="{{ $segTabAktif }}">
            <div class="field" style="margin-bottom:0;">
                <label>Bulan</label>
                <select class="select-pill" name="bulan" onchange="this.form.submit()">
                    @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $i => $nama)
                        <option value="{{ $i + 1 }}" {{ $bulan == $i + 1 ? 'selected' : '' }}>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Tahun</label>
                <select class="select-pill" name="tahun" onchange="this.form.submit()">
                    @for ($y = now()->year + 1; $y >= now()->year - 2; $y--)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
    </div>

    <div style="display:flex; gap:4px; margin-bottom:22px; border-bottom:1px solid var(--line);">
        <button type="button" id="segtab-segmentasi-btn" onclick="switchSegmentasiTab('segmentasi')" style="padding:10px 4px; margin-right:22px; background:none; border:none; border-bottom:2px solid {{ $segTabAktif === 'segmentasi' ? 'var(--accent)' : 'transparent' }}; font-family:inherit; font-size:14px; font-weight:{{ $segTabAktif === 'segmentasi' ? '700' : '600' }}; color:var(--{{ $segTabAktif === 'segmentasi' ? 'ink' : 'ink-muted' }}); cursor:pointer;">Segmentasi Mitra</button>
        <button type="button" id="segtab-portfolio-btn" onclick="switchSegmentasiTab('portfolio')" style="padding:10px 4px; margin-right:22px; background:none; border:none; border-bottom:2px solid {{ $segTabAktif === 'portfolio' ? 'var(--accent)' : 'transparent' }}; font-family:inherit; font-size:14px; font-weight:{{ $segTabAktif === 'portfolio' ? '700' : '600' }}; color:var(--{{ $segTabAktif === 'portfolio' ? 'ink' : 'ink-muted' }}); cursor:pointer;">Portfolio &amp; Assortment Health</button>
    </div>

    <div id="segtab-segmentasi" style="display:{{ $segTabAktif === 'segmentasi' ? '' : 'none' }};">
    <section style="display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Jumlah Mitra</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $mitraList->count() }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Total Omset {{ $periodeLabel }}</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $rp($totalOmset) }}</div>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Daftar Mitra &mdash; {{ $segmen }}</div>
            <div class="card-hint">{{ $mitraList->count() }} mitra</div>
        </div>
        <div class="table-scroll" style="max-height:none; overflow-y:visible;">
            <table>
                <thead><tr><th>Mitra</th><th>KAE</th><th>Omset</th><th>Target</th><th>% vs Target</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($mitraListPage as $m)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $m->nama }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $m->kode_mitra }}</div>
                            </td>
                            <td>
                                @if ($m->kae_code)
                                    <span style="display:inline-block; padding:2px 8px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:11px; font-weight:600;">{{ \App\Models\User::kaeNameMap()[$m->kae_code] ?? $m->kae_code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="tnum">{{ $rp($m->omset) }}</td>
                            <td class="tnum">{{ $rp($m->target) }}</td>
                            <td class="tnum">
                                <span class="chip chip-{{ \App\Services\AchievementStatus::color($m->status) }}">{{ $m->pct }}%</span>
                            </td>
                            <td>
                                <span class="chip chip-{{ \App\Services\AchievementStatus::color($m->status) }}">{{ \App\Services\AchievementStatus::label($m->status) }}</span>
                            </td>
                            <td><a href="{{ route('mitra.show', $m->id) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px;">Lihat detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada mitra di segmen ini untuk {{ $periodeLabel }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div style="margin-top:16px;">{{ $mitraListPage->appends(['tab' => 'segmentasi'])->links() }}</div>
    </div>

    <div id="segtab-portfolio" style="display:{{ $segTabAktif === 'portfolio' ? '' : 'none' }};">
    @php
        $tierLabels = ['champion' => 'Champion', 'explorer' => 'Explorer', 'traditional' => 'Traditional', 'cherry_picker' => 'Cherry Picker'];
        $tierTotal = array_sum($segmentSummary['tier_counts']);
        $tierSegments = collect($segmentSummary['tier_counts'])->map(fn ($count, $key) => [
            'label' => $tierLabels[$key].' ('.$count.')',
            'pct' => $tierTotal > 0 ? round($count / $tierTotal * 100, 1) : 0,
        ])->values()->all();

        $fastMovingTotal = collect($segmentSummary['top_fast_moving'])->sum('qty');
        $fastMovingSegments = collect($segmentSummary['top_fast_moving'])->map(fn ($s) => [
            'label' => $s['sku'].' ('.$s['qty'].')',
            'pct' => $fastMovingTotal > 0 ? round($s['qty'] / $fastMovingTotal * 100, 1) : 0,
        ])->all();

        $wsColorFor = fn ($v) => $v === null ? 'neutral' : ($v < 25 ? 'good' : ($v <= 50 ? 'warn' : 'critical'));
        $heroColorFor = fn ($v) => $v === null ? 'neutral' : ($v < 60 ? 'good' : ($v <= 75 ? 'warn' : 'critical'));
        $npdTotal = $segmentSummary['npd_adopted'] + $segmentSummary['npd_not_adopted'];
        $npdRate = $npdTotal > 0 ? round($segmentSummary['npd_adopted'] / $npdTotal * 100, 1) : null;
    @endphp

    <section class="card" style="margin-top:20px;">
        <div class="card-head">
            <div>
                <div class="card-title">Ringkasan Portfolio &amp; Assortment Health</div>
                <div class="card-hint">Segmen {{ $segmen }} &middot; YTD {{ $tahunYtd }} &middot; {{ $tierTotal }} mitra dengan data</div>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1.3fr; gap:24px;">
            <div>
                <div class="info-label" style="margin-bottom:10px;">Distribusi Tier</div>
                @include('partials.doughnut-chart', ['segments' => $tierSegments, 'size' => 130, 'strokeWidth' => 18])
            </div>
            <div style="border-left:1px solid var(--line); padding-left:20px; box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                <div class="info-label" style="margin-bottom:10px;">Rata-rata Metrik Segmen</div>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12.5px; color:var(--ink-muted);">White Space</span>
                        @if ($segmentSummary['avg_white_space'] === null)
                            <span style="color:var(--ink-faint); font-size:12px;">—</span>
                        @else
                            <span class="chip chip-{{ $wsColorFor($segmentSummary['avg_white_space']) }}">{{ $segmentSummary['avg_white_space'] }}%</span>
                        @endif
                    </div>
                    @if ($segmentSummary['top_missing_sku']->isNotEmpty())
                        <button type="button" class="btn" style="width:auto; font-size:11px; padding:5px 10px; align-self:flex-start;" onclick="const d = document.getElementById('segment-missing-sku'); d.style.display = d.style.display === 'none' ? '' : 'none';">Lihat Produk White Space</button>
                    @endif
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12.5px; color:var(--ink-muted);">Hero SKU Concentration</span>
                        @if ($segmentSummary['avg_hero_sku'] === null)
                            <span style="color:var(--ink-faint); font-size:12px;">—</span>
                        @else
                            <span class="chip chip-{{ $heroColorFor($segmentSummary['avg_hero_sku']) }}">{{ $segmentSummary['avg_hero_sku'] }}%</span>
                        @endif
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12.5px; color:var(--ink-muted);">NPD Adoption Rate</span>
                        @if (! $segmentSummary['npd_active'])
                            <span style="color:var(--ink-faint); font-size:12px;">Tidak ada NPD aktif</span>
                        @else
                            <span class="chip chip-{{ $npdRate >= 50 ? 'good' : 'critical' }}">{{ $npdRate }}% ({{ $segmentSummary['npd_adopted'] }}/{{ $npdTotal }})</span>
                        @endif
                    </div>
                </div>
            </div>
            <div style="border-left:1px solid var(--line); padding-left:20px; box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                <div class="info-label" style="margin-bottom:10px;">Top 5 SKU Fast Moving (by Qty)</div>
                @include('partials.doughnut-chart', ['segments' => $fastMovingSegments, 'size' => 130, 'strokeWidth' => 18])
            </div>
        </div>

        @if ($segmentSummary['top_missing_sku']->isNotEmpty())
            <div id="segment-missing-sku" style="display:none; margin-top:20px; padding-top:16px; border-top:1px solid var(--line); box-shadow:inset 0 1px 2px rgba(0,0,0,0.06);">
                <div class="info-label" style="margin-bottom:10px;">Produk Paling Sering Jadi White Space (Segmen {{ $segmen }})</div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    @foreach ($segmentSummary['top_missing_sku'] as $s)
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:12.5px;">
                            <div>
                                <span style="font-weight:600;">{{ $s['nama'] }}</span>
                                <span style="color:var(--ink-faint);">&middot; {{ $s['brand'] }}</span>
                            </div>
                            <span class="chip chip-warn" style="flex:none;">belum dibeli {{ $s['count'] }} mitra</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <section class="card table-card" style="padding:0; margin-top:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div>
                <div class="card-title">Portfolio &amp; Assortment Health</div>
                <div class="card-hint">YTD {{ $tahunYtd }} &middot; dihitung otomatis dari histori order 1 Jan {{ $tahunYtd }} s/d hari ini</div>
            </div>
            <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;" onclick="const d = document.getElementById('portfolio-legend'); d.style.display = d.style.display === 'none' ? 'grid' : 'none';">Apa itu White Space, Hero SKU, &amp; Tier?</button>
        </div>

        <div id="portfolio-legend" style="display:none; padding:0 20px 18px; grid-template-columns:repeat(2, 1fr); gap:14px 24px; font-size:12px; color:var(--ink-muted); line-height:1.6;">
            <div>
                <b style="color:var(--ink);">White Space</b> &mdash; % SKU aktif (dalam brand yang sudah pernah dibeli mitra) yang belum pernah dia beli sama sekali. Indikasi potensi omzet yang belum ke-capture.
                <br>&lt;25% <span class="chip chip-good" style="padding:1px 6px;">Sehat</span> &middot; 25&ndash;50% <span class="chip chip-warn" style="padding:1px 6px;">Moderat</span> &middot; &gt;50% <span class="chip chip-critical" style="padding:1px 6px;">Kritis / Cherry Picker</span>
            </div>
            <div>
                <b style="color:var(--ink);">Hero SKU Concentration</b> &mdash; berapa persen omzet mitra bertumpu ke 3 SKU terlarisnya saja. Makin tinggi, makin rentan kalau salah satu produk itu turun tren.
                <br>&lt;60% <span class="chip chip-good" style="padding:1px 6px;">Sehat</span> &middot; 60&ndash;75% <span class="chip chip-warn" style="padding:1px 6px;">Peringatan</span> &middot; &gt;75% <span class="chip chip-critical" style="padding:1px 6px;">Berisiko Tinggi</span>
            </div>
            <div style="grid-column:1 / -1;">
                <b style="color:var(--ink);">Tier</b> &mdash; kuadran White Space &times; Hero SKU, tiap mitra selalu terklasifikasi ke salah satu:
                <div style="display:grid; grid-template-columns:auto 1fr 1fr; gap:1px; margin-top:10px; max-width:600px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden;">
                    <div style="background:var(--surface-alt);"></div>
                    <div style="background:var(--surface-alt); padding:8px 12px; font-size:11px; font-weight:600; color:var(--ink-muted); text-align:center;">Hero SKU Rendah (&lt;65%)</div>
                    <div style="background:var(--surface-alt); padding:8px 12px; font-size:11px; font-weight:600; color:var(--ink-muted); text-align:center;">Hero SKU Tinggi (&ge;65%)</div>

                    <div style="background:var(--surface-alt); padding:8px 10px; font-size:11px; font-weight:600; color:var(--ink-muted); text-align:center; writing-mode:vertical-rl; transform:rotate(180deg);">White Space Rendah (&lt;40%)</div>
                    <div style="background:var(--surface); padding:10px 12px;">
                        <span class="chip chip-good">Champion / Balanced Partner</span>
                        <div style="font-size:11px; color:var(--ink-muted); margin-top:6px; line-height:1.4;">Portfolio luas, belanja merata</div>
                    </div>
                    <div style="background:var(--surface); padding:10px 12px;">
                        <span class="chip chip-accent">Explorer / Concentrated Trial</span>
                        <div style="font-size:11px; color:var(--ink-muted); margin-top:6px; line-height:1.4;">Coba banyak SKU, tapi belanja numpuk ke sedikit favorit</div>
                    </div>

                    <div style="background:var(--surface-alt); padding:8px 10px; font-size:11px; font-weight:600; color:var(--ink-muted); text-align:center; writing-mode:vertical-rl; transform:rotate(180deg);">White Space Tinggi (&ge;40%)</div>
                    <div style="background:var(--surface); padding:10px 12px;">
                        <span class="chip chip-neutral">Traditional / Steady Niche</span>
                        <div style="font-size:11px; color:var(--ink-muted); margin-top:6px; line-height:1.4;">Setia ke SKU sedikit, tapi belanja merata di situ</div>
                    </div>
                    <div style="background:var(--surface); padding:10px 12px;">
                        <span class="chip chip-critical">Cherry Picker</span>
                        <div style="font-size:11px; color:var(--ink-muted); margin-top:6px; line-height:1.4;">Portfolio sempit &amp; numpuk &mdash; paling rapuh</div>
                    </div>
                </div>
            </div>
            <div>
                <b style="color:var(--ink);">NPD Adoption</b> &mdash; apakah mitra sudah order produk yang baru dirilis (ditandai admin di menu Input NPD, berlaku 90 hari). Ditampilkan sebagai badge terpisah di sebelah Tier, bukan syarat kualifikasi.
                <br><b style="color:var(--ink);">Lapsing</b> &mdash; badge muncul kalau mitra sudah 60+ hari nggak order, independen dari Tier.
            </div>
        </div>

        <div class="table-scroll" style="max-height:none; overflow-y:visible;">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th>
                        <th>Total Belanja YTD</th><th>Jml Order YTD</th><th>Avg / Order</th>
                        <th>Recency</th><th>Lunas</th><th>Minggu Favorit</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mitraListPage as $m)
                        @php $h = $kesehatanMitra[$m->id] ?? null; @endphp
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $m->nama }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $m->kode_mitra }}</div>
                            </td>
                            <td class="tnum">{{ $rp($h['total_omset'] ?? 0) }}</td>
                            <td class="tnum">{{ $h['jumlah_order'] ?? 0 }}</td>
                            <td class="tnum">{{ $rp($h['avg_order'] ?? 0) }}</td>
                            <td class="tnum">
                                @if (($h['recency_hari'] ?? null) === null)
                                    <span style="color:var(--ink-faint);">Belum pernah</span>
                                @else
                                    @php $recColor = $h['recency_hari'] <= 14 ? 'good' : ($h['recency_hari'] <= 30 ? 'warn' : 'critical'); @endphp
                                    <span class="chip chip-{{ $recColor }}">{{ $h['recency_hari'] }} hari lalu</span>
                                @endif
                            </td>
                            <td class="tnum">
                                @if (($h['rasio_lunas'] ?? null) === null)
                                    <span style="color:var(--ink-faint);">—</span>
                                @else
                                    @php $lunasColor = $h['rasio_lunas'] >= 90 ? 'good' : ($h['rasio_lunas'] >= 70 ? 'warn' : 'critical'); @endphp
                                    <span class="chip chip-{{ $lunasColor }}">{{ $h['rasio_lunas'] }}%</span>
                                @endif
                            </td>
                            <td>{{ $h['minggu_favorit'] ?? '—' }}</td>
                            <td>
                                @if (($h['jumlah_order'] ?? 0) > 0)
                                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;" onclick="const d = document.getElementById('sku-{{ $m->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Portfolio &amp; Assortment Health</button>
                                @endif
                            </td>
                        </tr>
                        @if (($h['jumlah_order'] ?? 0) > 0)
                            <tr id="sku-{{ $m->id }}" style="display:none;">
                                <td colspan="8" style="background:var(--surface-alt);">
                                    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; padding:12px 4px; margin-bottom:4px;">
                                        <div>
                                            <div class="info-label" style="margin-bottom:6px;">White Space</div>
                                            @if (($h['white_space_pct'] ?? null) === null)
                                                <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                            @else
                                                @php $wsColor = ['sehat' => 'good', 'moderat' => 'warn', 'kritis' => 'critical'][$h['white_space_status']]; @endphp
                                                <span class="chip chip-{{ $wsColor }}">{{ $h['white_space_pct'] }}%</span>
                                            @endif
                                        </div>
                                        <div style="margin-left:-8px; padding-left:8px; border-left:1px solid var(--line); box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                                            <div class="info-label" style="margin-bottom:6px;">Hero SKU Concentration</div>
                                            @if (($h['hero_sku_pct'] ?? null) === null)
                                                <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                            @else
                                                @php $heroColor = ['sehat' => 'good', 'peringatan' => 'warn', 'berisiko' => 'critical'][$h['hero_sku_status']]; @endphp
                                                <span class="chip chip-{{ $heroColor }}">{{ $h['hero_sku_pct'] }}%</span>
                                            @endif
                                        </div>
                                        <div style="margin-left:-8px; padding-left:8px; border-left:1px solid var(--line); box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                                            <div class="info-label" style="margin-bottom:6px;">NPD</div>
                                            @if (($h['npd_status'] ?? null) === null)
                                                <span style="color:var(--ink-faint); font-size:12px;">Tidak ada NPD aktif</span>
                                            @elseif ($h['npd_status'] === 'adopted')
                                                <span class="chip chip-good">Adopted</span>
                                            @else
                                                <span class="chip chip-critical">Not Adopted</span>
                                            @endif
                                        </div>
                                        <div style="margin-left:-8px; padding-left:8px; border-left:1px solid var(--line); box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                                            <div class="info-label" style="margin-bottom:6px;">Tier</div>
                                            <span class="chip chip-{{ \App\Services\MitraHealthService::tierColor($h['tier'] ?? null) }}">{{ \App\Services\MitraHealthService::tierLabel($h['tier'] ?? null) }}</span>
                                            @if ($h['lapsing'] ?? false)
                                                <span class="chip chip-critical" style="margin-left:4px;">Lapsing 60+ hari</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; padding:14px 4px 8px; border-top:1px solid var(--line); box-shadow:inset 0 1px 2px rgba(0,0,0,0.06);">
                                        <div>
                                            <div class="info-label" style="margin-bottom:8px;">Kontribusi Brand</div>
                                            @include('partials.doughnut-chart', ['segments' => collect($h['brand_kontribusi'])->map(fn ($b) => ['label' => $b['brand'], 'pct' => $b['pct']])->all(), 'size' => 100, 'strokeWidth' => 14])
                                        </div>
                                        <div style="margin-left:-10px; padding-left:10px; border-left:1px solid var(--line); box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                                            <div class="info-label" style="margin-bottom:8px;">Top 5 SKU (by Qty)</div>
                                            @forelse ($h['top5_sku'] as $s)
                                                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px; gap:8px;">
                                                    <span style="color:var(--ink-muted);">{{ $s->sku }}</span>
                                                    <span class="tnum" style="font-weight:600; white-space:nowrap;">{{ (int) $s->total_qty }}</span>
                                                </div>
                                            @empty
                                                <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                            @endforelse
                                        </div>
                                        <div style="margin-left:-10px; padding-left:10px; border-left:1px solid var(--line); box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                                            <div class="info-label" style="margin-bottom:8px;">Bottom 5 SKU (by Qty)</div>
                                            @forelse ($h['bottom5_sku'] as $s)
                                                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px; gap:8px;">
                                                    <span style="color:var(--ink-muted);">{{ $s->sku }}</span>
                                                    <span class="tnum" style="font-weight:600; white-space:nowrap;">{{ (int) $s->total_qty }}</span>
                                                </div>
                                            @empty
                                                <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:20px; padding:16px 4px 8px; border-top:1px solid var(--line); box-shadow:inset 0 1px 2px rgba(0,0,0,0.06); margin-top:8px;">
                                        <div>
                                            <div class="info-label" style="margin-bottom:8px;">Hero SKU (Top 3 by Omzet) &mdash; {{ $h['hero_sku_pct'] ?? 0 }}% dari total</div>
                                            @forelse ($h['top3_sku_omzet'] as $s)
                                                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px; gap:8px;">
                                                    <span style="color:var(--ink-muted);">{{ $s->sku }}</span>
                                                    <span class="tnum" style="font-weight:600; white-space:nowrap;">{{ $rp($s->total) }}</span>
                                                </div>
                                            @empty
                                                <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                            @endforelse
                                        </div>
                                        <div style="margin-left:-10px; padding-left:10px; border-left:1px solid var(--line); box-shadow:inset 1px 0 2px rgba(0,0,0,0.06);">
                                            <div class="info-label" style="margin-bottom:8px;">White Space &mdash; SKU Belum Dibeli per Brand</div>
                                            @forelse ($h['white_space_by_brand'] ?? collect() as $bw)
                                                <div style="margin-bottom:10px;">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:4px;">
                                                        <span style="font-size:12px; font-weight:600;">{{ $bw['brand'] }}</span>
                                                        <span class="tnum" style="font-size:11px; color:var(--ink-muted);">{{ $bw['missing'] }}/{{ $bw['catalog'] }} &middot; {{ $bw['pct'] }}%</span>
                                                    </div>
                                                    @forelse ($bw['missing_sku']->take(4) as $s)
                                                        <div style="font-size:11.5px; color:var(--ink-muted); margin-bottom:2px; padding-left:4px;">{{ $s->nama }}</div>
                                                    @empty
                                                        <div style="font-size:11.5px; color:var(--ink-faint); padding-left:4px;">Sudah lengkap.</div>
                                                    @endforelse
                                                    @if ($bw['missing_sku']->count() > 4)
                                                        <div style="font-size:11px; color:var(--ink-faint); padding-left:4px;">+{{ $bw['missing_sku']->count() - 4 }} lainnya</div>
                                                    @endif
                                                </div>
                                            @empty
                                                <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" style="color:var(--ink-muted);">Belum ada mitra di segmen ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div style="margin-top:16px;">{{ $mitraListPage->appends(['tab' => 'portfolio'])->links() }}</div>
    </div>

    <script>
        function switchSegmentasiTab(tab) {
            ['segmentasi', 'portfolio'].forEach(function (t) {
                document.getElementById('segtab-' + t).style.display = (t === tab) ? '' : 'none';
                var btn = document.getElementById('segtab-' + t + '-btn');
                btn.style.borderBottomColor = (t === tab) ? 'var(--accent)' : 'transparent';
                btn.style.color = (t === tab) ? 'var(--ink)' : 'var(--ink-muted)';
                btn.style.fontWeight = (t === tab) ? '700' : '600';
            });
            document.querySelector('form[action*="segmentasi"] input[name="tab"]').value = tab;
        }
    </script>
@endsection
