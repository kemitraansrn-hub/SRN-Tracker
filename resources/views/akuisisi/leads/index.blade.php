@extends('layouts.app')

@section('content')
    @php
        $f = $filters;
        $url = fn (array $params = []) => route('akuisisi-leads.index', $params);
        $number = fn ($value) => number_format((int) $value, 0, ',', '.');
    @endphp

    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:4px;">
        <div>
            <h1 class="display" style="font-size:24px;">Database Lead</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Periode tanggal masuk: <strong>{{ $f->periodLabel() }}</strong>
            </div>
        </div>
        @if (auth()->user()->hasAdminAccess())
            <div style="display:flex; gap:8px;">
                <a href="{{ route('akuisisi-leads.imports.create') }}" class="btn" style="width:auto;">Import Lead</a>
                <a href="{{ route('akuisisi-leads.create') }}" class="btn btn-primary" style="width:auto;">+ Tambah Lead</a>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success" style="margin-top:16px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('akuisisi-leads.index') }}" style="margin-top:16px;">
        <input type="hidden" name="tab" value="{{ $f->tab }}">

        {{-- Filter periode --}}
        <div class="field-row" style="align-items:flex-end;">
            <div class="field" style="margin-bottom:0;">
                <label>Periode</label>
                <select name="period" class="select-pill" onchange="akuisisiTogglePeriodFields(this.value)">
                    @foreach (['all' => 'Semua waktu', 'day' => 'Tanggal', 'month' => 'Bulan', 'range' => 'Rentang tanggal'] as $value => $label)
                        <option value="{{ $value }}" {{ $f->period === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" id="akuisisi-period-day" style="margin-bottom:0; {{ $f->period !== 'day' ? 'display:none;' : '' }}">
                <label>Tanggal masuk</label>
                <input type="date" name="day" value="{{ $f->day }}">
            </div>
            <div class="field" id="akuisisi-period-month" style="margin-bottom:0; {{ $f->period !== 'month' ? 'display:none;' : '' }}">
                <label>Bulan masuk</label>
                <input type="month" name="month" value="{{ $f->month ?? today()->format('Y-m') }}">
            </div>
            <div class="field" id="akuisisi-period-range-from" style="margin-bottom:0; {{ $f->period !== 'range' ? 'display:none;' : '' }}">
                <label>Dari</label>
                <input type="date" name="from" value="{{ $f->from }}">
            </div>
            <div class="field" id="akuisisi-period-range-to" style="margin-bottom:0; {{ $f->period !== 'range' ? 'display:none;' : '' }}">
                <label>Sampai</label>
                <input type="date" name="to" value="{{ $f->to }}">
            </div>
            <button type="submit" class="btn" style="width:auto;">Terapkan</button>
        </div>

        <script>
            function akuisisiTogglePeriodFields(period) {
                document.getElementById('akuisisi-period-day').style.display = period === 'day' ? '' : 'none';
                document.getElementById('akuisisi-period-month').style.display = period === 'month' ? '' : 'none';
                document.getElementById('akuisisi-period-range-from').style.display = period === 'range' ? '' : 'none';
                document.getElementById('akuisisi-period-range-to').style.display = period === 'range' ? '' : 'none';
            }
        </script>

        {{-- Kartu rekap (2x3) + funnel di sebelah kanan --}}
        <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-top:20px; align-items:stretch;">
            @php
                $statCard = function (string $label, $value, ?string $href = null, ?string $badge = null, string $tone = 'slate', string $icon = '') {
                    return compact('label', 'value', 'href', 'badge', 'tone', 'icon');
                };
                $icons = [
                    'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21a2 2 0 0 1-3.46 0" stroke-linecap="round"/>',
                    'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
                    'refresh' => '<path d="M21 2v6h-6" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 22v-6h6" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16" stroke-linecap="round" stroke-linejoin="round"/>',
                    'fire' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" stroke-linejoin="round"/>',
                    'sun' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3" stroke-linecap="round"/><line x1="12" y1="21" x2="12" y2="23" stroke-linecap="round"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke-linecap="round"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke-linecap="round"/><line x1="1" y1="12" x2="3" y2="12" stroke-linecap="round"/><line x1="21" y1="12" x2="23" y2="12" stroke-linecap="round"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke-linecap="round"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke-linecap="round"/>',
                    'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>',
                ];
                $tones = [
                    'warn' => ['bg' => 'var(--warn-soft)', 'ink' => 'var(--warn)'],
                    'slate' => ['bg' => 'var(--surface-alt)', 'ink' => 'var(--ink-muted)'],
                    'accent' => ['bg' => 'var(--accent-soft)', 'ink' => 'var(--accent-ink)'],
                    'critical' => ['bg' => 'var(--critical-soft)', 'ink' => 'var(--critical)'],
                    'highlight' => ['bg' => 'var(--highlight-soft)', 'ink' => 'var(--highlight)'],
                    'good' => ['bg' => 'var(--good-soft)', 'ink' => 'var(--good)'],
                ];
                $cards = [
                    $statCard('Follow-up Hari Ini', $stats['todo_today'], $url(['tab' => 'todo']), $stats['todo_overdue'] ? '+'.$number($stats['todo_overdue']).' terlewat' : null, 'warn', 'bell'),
                    $statCard('Total Database', $stats['total'], null, null, 'slate', 'database'),
                    $statCard('In Progress (Aktif)', $stats['active'], $url($f->periodQuery(['tab' => 'active'])), null, 'accent', 'refresh'),
                    $statCard('Hot Leads (Aktif)', $stats['hot'], $url($f->periodQuery(['tab' => 'active', 'priority' => 'HOT'])), null, 'critical', 'fire'),
                    $statCard('Warm Leads (Aktif)', $stats['warm'], $url($f->periodQuery(['tab' => 'active', 'priority' => 'WARM'])), null, 'highlight', 'sun'),
                    $statCard('Closed Deal', $stats['closed'], $closingStatusId ? $url($f->periodQuery(['tab' => 'archive', 'status' => $closingStatusId])) : null, null, 'good', 'check-circle'),
                ];
            @endphp
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
                @foreach ($cards as $card)
                    @php
                        $tag = $card['href'] ? 'a' : 'div';
                        $tone = $tones[$card['tone']];
                        $highlightBorder = in_array($card['tone'], ['warn', 'good'], true) ? "border-color:{$tone['ink']};" : '';
                    @endphp
                    <{!! $tag !!} @if ($card['href']) href="{{ $card['href'] }}" @endif class="card" style="padding:14px 16px; text-decoration:none; display:block; {{ $highlightBorder }}">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                            <div style="font-size:11.5px; color:{{ $highlightBorder ? $tone['ink'] : 'var(--ink-muted)' }}; font-weight:600;">{{ $card['label'] }}</div>
                            <div style="width:34px; height:34px; border-radius:10px; background:{{ $tone['bg'] }}; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="{{ $tone['ink'] }}" stroke-width="2">{!! $icons[$card['icon']] !!}</svg>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px; margin-top:8px;">
                            <span class="tnum" style="font-size:22px; font-weight:700; color:var(--ink);">{{ $number($card['value']) }}</span>
                            @if ($card['badge'])
                                <span class="chip chip-critical" style="font-size:10.5px;">{{ $card['badge'] }}</span>
                            @endif
                        </div>
                    </{!! $tag !!}>
                @endforeach
            </div>

            {{-- Funnel pipeline --}}
            <section class="card" style="display:flex; flex-direction:column;">
                <div class="card-head">
                    <div class="card-title">Funnel Pipeline</div>
                    @if ($funnel['unstaged'])
                        <a href="#rincian-tahapan" class="card-hint" style="text-decoration:none;" title="{{ $number($funnel['unstaged']) }} lead belum punya tahapan.">
                            {{ $number($funnel['unstaged']) }} belum bertahap
                        </a>
                    @endif
                </div>

                @if ($funnel['base'] === 0)
                    <div style="color:var(--ink-muted); font-size:13px; text-align:center; padding:16px; margin:auto;">Belum ada lead yang ditahapkan. Tahapan terisi saat CS mencatat follow-up.</div>
                @else
                    <div style="display:flex; flex-direction:column; gap:10px; justify-content:space-between; flex:1;">
                        @foreach ($funnel['steps'] as $index => $step)
                            @php
                                $width = $step['reached'] ? max($step['reached'] / $funnel['base'] * 100, 4) : 0;
                                $previousName = $funnel['steps'][$index - 1]['name'] ?? null;
                                $pct = $step['conversion'] === null ? null : number_format($step['conversion'] * 100, $step['conversion'] * 100 < 10 ? 1 : 0, ',', '.').'%';
                                $tooltip = $step['is_closing']
                                    ? "Closing dari lead yang sudah ditahapkan: {$number($step['reached'])}"
                                    : "Mencapai tahap ini: {$number($step['reached'])} · Aktif: {$number($step['active'])} · Gugur: {$number($step['dropped'])} · Closing: {$number($step['closed'])}";
                                $tooltip .= ($previousName && $pct) ? " · Konversi dari {$previousName}: {$pct}" : '';
                            @endphp
                            <div style="display:grid; grid-template-columns:90px 1fr 70px; align-items:center; gap:8px;" title="{{ $tooltip }}">
                                <span style="font-size:11.5px; font-weight:600; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    @if ($step['is_closing'])
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--good)" stroke-width="2.5" style="vertical-align:-1px; margin-right:2px;"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @endif
                                    {{ $step['name'] }}
                                </span>
                                <span style="height:12px; background:var(--surface-alt); border-radius:4px; overflow:hidden;">
                                    <span style="display:block; height:100%; width:{{ round($width, 2) }}%; background:{{ $step['is_closing'] ? 'var(--good)' : 'var(--accent)' }}; border-radius:4px;"></span>
                                </span>
                                <span style="text-align:right; font-size:11.5px; white-space:nowrap;">
                                    <span class="tnum" style="font-weight:700;">{{ $number($step['reached']) }}</span>
                                    @if ($index > 0 && $pct)
                                        <span style="color:var(--ink-faint);">· {{ $pct }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Rincian lead per tahapan --}}
        <details class="card" id="rincian-tahapan" style="margin-top:12px; padding:0;">
            <summary style="cursor:pointer; padding:16px;">
                <span class="card-title" style="display:inline;">Rincian lead per tahapan</span>
                <div class="card-hint" style="margin-top:2px;">Hasil follow-up terakhir lead di tiap tahapan pipeline. Klik angka untuk melihat daftarnya.</div>
            </summary>
            <div class="table-scroll" style="max-height:none; border-top:1px solid var(--line);">
                <table>
                    <thead>
                        <tr>
                            <th>Tahapan</th>
                            <th style="text-align:right;">Aktif</th>
                            @foreach ($matrix['columns'] as $column)
                                <th style="text-align:right;">{{ $column->name }}</th>
                            @endforeach
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix['rows'] as $row)
                            <tr>
                                <td style="{{ $row['key'] === 'none' ? 'font-style:italic; color:var(--ink-muted);' : 'font-weight:600;' }}">{{ $row['name'] }}</td>
                                @foreach (['active' => null] + $matrix['columns']->keyBy('id')->all() as $key => $column)
                                    @php $count = $row['cells'][$key] ?? 0; @endphp
                                    <td style="text-align:right;">
                                        @if ($count)
                                            <a href="{{ $url($f->periodQuery($key === 'active' ? ['tab' => 'active', 'pipeline' => $row['key']] : ['tab' => 'archive', 'pipeline' => $row['key'], 'status' => $key])) }}"
                                                style="font-weight:600; text-decoration:none; color:{{ $key === 'active' ? 'var(--accent-ink)' : ($column?->is_closed_deal ? 'var(--good)' : 'var(--ink)') }};">{{ $count }}</a>
                                        @else
                                            <span style="color:var(--ink-faint);">–</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td style="text-align:right; font-weight:700;">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        {{-- Daftar lead --}}
        <section class="card table-card" style="margin-top:12px; padding:0;">
            <div style="display:flex; flex-wrap:wrap; gap:6px; padding:14px 16px; border-bottom:1px solid var(--line);">
                @foreach (['todo' => 'To-Do Hari Ini', 'active' => 'Active Pipeline', 'archive' => 'Closed & Archive'] as $tab => $label)
                    <a href="{{ $url($f->query(['tab' => $tab])) }}" class="btn {{ $f->tab === $tab ? 'btn-primary' : '' }}" style="width:auto; font-size:12px; padding:6px 12px;">
                        {{ $label }} ({{ $number($tabCounts[$tab]) }})
                    </a>
                @endforeach
            </div>

            <div class="field-row" style="padding:14px 16px; background:var(--surface-alt); border-bottom:1px solid var(--line); margin-bottom:0; align-items:flex-end;">
                <div class="field" style="margin-bottom:0; flex:1; min-width:200px;">
                    <label>Cari</label>
                    <input type="text" name="q" value="{{ $f->search }}" placeholder="Nama, kota, nomor WA...">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Prioritas</label>
                    <select name="priority" class="select-pill">
                        <option value="">Semua</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->code }}" {{ $f->priority === $priority->code ? 'selected' : '' }}>{{ $priority->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Pipeline</label>
                    <select name="pipeline" class="select-pill">
                        <option value="">Semua</option>
                        @foreach ($pipelineStages as $stage)
                            <option value="{{ $stage->id }}" {{ $f->pipeline === (string) $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
                        @endforeach
                        <option value="none" {{ $f->pipeline === 'none' ? 'selected' : '' }}>Belum ada tahapan</option>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Hasil Follow-up</label>
                    <select name="status" class="select-pill">
                        <option value="">Semua</option>
                        <option value="none" {{ $f->status === 'none' ? 'selected' : '' }}>Belum di-follow-up</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" {{ $f->status === (string) $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn" style="width:auto;">Terapkan</button>
                @if ($f->activeFilterCount())
                    <a href="{{ $url(['tab' => $f->tab]) }}" class="btn" style="width:auto;">Reset ({{ $f->activeFilterCount() }})</a>
                @endif
            </div>

            <div class="table-scroll" style="max-height:none;">
                <table style="table-layout:fixed;">
                    <colgroup>
                        <col style="width:200px;"><col style="width:150px;"><col style="width:150px;"><col style="width:130px;"><col><col style="width:140px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Calon Mitra</th>
                            <th>Prioritas &amp; Channel</th>
                            <th>Pipeline</th>
                            <th>Next Follow-up</th>
                            <th>Catatan Terakhir</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leads as $lead)
                            @include('akuisisi.leads._row')
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px 16px; color:var(--ink-muted);">
                                    @if ($f->search !== '')
                                        Tidak ada lead yang cocok dengan "{{ $f->search }}".
                                    @elseif ($f->hasListFilters() || $f->hasPeriod())
                                        Tidak ada lead yang cocok dengan filter.
                                    @elseif ($f->tab === 'todo')
                                        Tidak ada follow-up untuk hari ini.
                                    @elseif ($f->tab === 'archive')
                                        Belum ada lead yang diarsipkan.
                                    @else
                                        Belum ada data calon mitra.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; padding:12px 16px; border-top:1px solid var(--line); font-size:12.5px; color:var(--ink-muted);">
                <div>Menampilkan <strong>{{ $leads->count() }}</strong> dari <strong>{{ $number($leads->total()) }}</strong> calon mitra{{ $f->tab === 'todo' ? ' · termasuk jadwal yang terlewat' : '' }}</div>
            </div>
        </section>
    </form>

    <div style="margin-top:16px;">{{ $leads->onEachSide(1)->links() }}</div>
@endsection
