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

        {{-- Kartu rekap + funnel --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-top:20px;">
            @php
                $statCard = function (string $label, $value, ?string $href = null, ?string $badge = null, string $tone = 'ink') {
                    return compact('label', 'value', 'href', 'badge', 'tone');
                };
                $cards = [
                    $statCard('Follow-up Hari Ini', $stats['todo_today'], $url(['tab' => 'todo']), $stats['todo_overdue'] ? '+'.$number($stats['todo_overdue']).' terlewat' : null, 'warn'),
                    $statCard('Total Database', $stats['total']),
                    $statCard('In Progress (Aktif)', $stats['active'], $url($f->periodQuery(['tab' => 'active']))),
                    $statCard('Hot Leads (Aktif)', $stats['hot'], $url($f->periodQuery(['tab' => 'active', 'priority' => 'HOT'])), null, 'critical'),
                    $statCard('Warm Leads (Aktif)', $stats['warm'], $url($f->periodQuery(['tab' => 'active', 'priority' => 'WARM'])), null, 'warn'),
                    $statCard('Closed Deal', $stats['closed'], $closingStatusId ? $url($f->periodQuery(['tab' => 'archive', 'status' => $closingStatusId])) : null, null, 'good'),
                ];
            @endphp
            @foreach ($cards as $card)
                @php $tag = $card['href'] ? 'a' : 'div'; @endphp
                <{!! $tag !!} @if ($card['href']) href="{{ $card['href'] }}" @endif class="card" style="padding:14px 16px; text-decoration:none; display:block;">
                    <div style="font-size:11.5px; color:var(--ink-muted); font-weight:600;">{{ $card['label'] }}</div>
                    <div style="display:flex; align-items:center; gap:6px; margin-top:4px;">
                        <span class="tnum" style="font-size:22px; font-weight:700; color:var(--ink);">{{ $number($card['value']) }}</span>
                        @if ($card['badge'])
                            <span class="chip chip-critical" style="font-size:10.5px;">{{ $card['badge'] }}</span>
                        @endif
                    </div>
                </{!! $tag !!}>
            @endforeach
        </div>

        {{-- Funnel pipeline --}}
        <section class="card" style="margin-top:12px;">
            <div class="card-head">
                <div class="card-title">Funnel Pipeline</div>
                @if ($funnel['unstaged'])
                    <a href="#rincian-tahapan" class="card-hint" style="text-decoration:none;" title="{{ $number($funnel['unstaged']) }} lead belum punya tahapan.">
                        {{ $number($funnel['unstaged']) }} belum bertahap
                    </a>
                @endif
            </div>

            @if ($funnel['base'] === 0)
                <div style="color:var(--ink-muted); font-size:13px; text-align:center; padding:16px;">Belum ada lead yang ditahapkan. Tahapan terisi saat CS mencatat follow-up.</div>
            @else
                <div style="display:flex; flex-direction:column; gap:10px;">
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
                        <div style="display:grid; grid-template-columns:110px 1fr 80px; align-items:center; gap:10px;" title="{{ $tooltip }}">
                            <span style="font-size:12px; font-weight:600; color:var(--ink);">
                                {{ $step['is_closing'] ? '✓ ' : '' }}{{ $step['name'] }}
                            </span>
                            <span style="height:14px; background:var(--surface-alt); border-radius:4px; overflow:hidden;">
                                <span style="display:block; height:100%; width:{{ round($width, 2) }}%; background:{{ $step['is_closing'] ? 'var(--good)' : 'var(--accent)' }}; border-radius:4px;"></span>
                            </span>
                            <span style="text-align:right; font-size:12px; white-space:nowrap;">
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
