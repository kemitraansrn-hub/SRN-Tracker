@extends('layouts.app')

@php
    $rp = fn ($v) => $v !== null ? 'Rp'.number_format((float) $v, 0, ',', '.') : '—';
    $statusChip = function ($status) {
        return match ($status) {
            'proses' => '<span class="chip chip-warn">Proses</span>',
            'done' => '<span class="chip chip-good">Done</span>',
            'batal' => '<span class="chip chip-critical">Batal</span>',
            default => $status,
        };
    };
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Special Deal</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $totalCount }} deal pada Q{{ $kuartal }} {{ $tahun }}
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('special-deal.upload.show') }}" class="btn" style="width:auto;">Upload Kuartal</a>
            @endif
            <a href="{{ route('special-deal.create') }}" class="btn btn-primary" style="width:auto;">+ Ajukan Special Deal</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('special-deal.index') }}" class="field-row" style="align-items:flex-end;">
        <div class="field" style="margin-bottom:0;">
            <label>Kuartal</label>
            <select name="kuartal" class="select-pill" onchange="this.form.submit()">
                @foreach ([1, 2, 3, 4] as $q)
                    <option value="{{ $q }}" {{ $kuartal == $q ? 'selected' : '' }}>Q{{ $q }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Tahun</label>
            <select name="tahun" class="select-pill" onchange="this.form.submit()">
                @for ($y = now()->year + 1; $y >= now()->year - 2; $y--)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" class="select-pill" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                @foreach (\App\Http\Controllers\SpecialDealController::STATUSES as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </form>

    @forelse ($groups as $segmen => $group)
        @php $s = $group['summary']; @endphp
        <section class="card table-card" style="padding:0; margin-top:20px;">
            <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
                <div>
                    <div class="card-title">{{ $segmen }}</div>
                    <div class="card-hint">{{ $s['count'] }} deal &middot; {{ $s['done_count'] }} done</div>
                </div>
                <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:12.5px;">
                    <div>
                        <div class="info-label">Total Target Q{{ $kuartal }}</div>
                        <div class="tnum" style="font-weight:700;">{{ $rp($s['target_sum']) }}</div>
                    </div>
                    <div>
                        <div class="info-label">Total NOM</div>
                        <div class="tnum" style="font-weight:700;">{{ $rp($s['nom_sum']) }}</div>
                    </div>
                    <div>
                        <div class="info-label">ACH %</div>
                        <div class="tnum" style="font-weight:700; color:{{ $s['ach_pct'] !== null && $s['ach_pct'] >= 100 ? 'var(--good)' : 'var(--ink)' }};">{{ $s['ach_pct'] !== null ? $s['ach_pct'].'%' : '—' }}</div>
                    </div>
                    <div>
                        <div class="info-label">Gap SD</div>
                        <div class="tnum" style="font-weight:700; color:{{ $s['gap_sum'] < 0 ? 'var(--critical)' : 'var(--good)' }};">{{ $rp($s['gap_sum']) }}</div>
                    </div>
                </div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>KAE</th><th>Mitra</th><th>Status</th>
                            <th>Target Monthly</th><th>Target Kuartal</th>
                            <th>Budget %</th><th>NOM</th><th>Subsidi</th>
                            @foreach ($bulanLabels as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th>Q{{ $kuartal }} SD</th><th>ACH %</th><th>Gap SD</th><th style="min-width:230px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['deals'] as $deal)
                            <tr>
                                <td>{{ $deal->kae->name ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('mitra.show', $deal->mitra) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $deal->mitra->nama ?? '—' }}</a>
                                </td>
                                <td>{!! $statusChip($deal->status) !!}</td>
                                <td class="tnum">{{ $rp($deal->targetMonthly()) }}</td>
                                <td class="tnum">{{ $rp($deal->target_kuartal) }}</td>
                                <td class="tnum">{{ $deal->budget_persen !== null ? number_format($deal->budget_persen, 2).'%' : '—' }}</td>
                                <td class="tnum">{{ $rp($deal->nominalReward()) }}</td>
                                <td style="font-size:12px;">{{ $deal->subsidi ?: '—' }}</td>
                                @foreach ($deal->bulan_aktual as $total)
                                    <td class="tnum">{{ $rp($total) }}</td>
                                @endforeach
                                <td class="tnum" style="font-weight:600;">{{ $rp($deal->q_sd) }}</td>
                                <td class="tnum" style="font-weight:600; color:{{ $deal->ach_pct !== null && $deal->ach_pct >= 100 ? 'var(--good)' : 'var(--ink)' }};">{{ $deal->ach_pct !== null ? $deal->ach_pct.'%' : '—' }}</td>
                                <td class="tnum" style="color:{{ $deal->gap !== null && $deal->gap < 0 ? 'var(--critical)' : 'var(--good)' }};">{{ $rp($deal->gap) }}</td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="{{ route('special-deal.edit', $deal) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px; white-space:nowrap;">Edit</a>
                                        <a href="{{ route('special-deal.mou', $deal) }}" class="link-action" style="color:var(--ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px; white-space:nowrap;">Cetak MOU</a>
                                        @if (auth()->user()->isAdmin())
                                            <form method="POST" action="{{ route('special-deal.destroy', $deal) }}" onsubmit="return confirm('Hapus special deal untuk {{ $deal->mitra->nama ?? 'mitra ini' }}? Data tidak bisa dikembalikan.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="card" style="margin-top:20px; color:var(--ink-muted);">Belum ada special deal untuk Q{{ $kuartal }} {{ $tahun }}.</div>
    @endforelse
@endsection
