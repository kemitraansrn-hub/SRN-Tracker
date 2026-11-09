@extends('layouts.app')

@php
    $canEdit = auth()->user()->hasAdminAccess();
@endphp

@section('content')
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:20px;">
        <div>
            <h1 class="display" style="font-size:24px;">Performa Channel</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">Input manual Lead, Prospect, Closing, Biaya &amp; Investasi Mitra per channel per bulan &mdash; sumber data Dashboard &gt; Akuisisi.</div>
        </div>
        @include('partials.bulan-tahun-filter', ['action' => route('akuisisi-channel-performance.index'), 'bulan' => $bulan, 'tahun' => $tahun, 'isBulanIni' => $isBulanIni])
    </div>

    @if (session('status'))
        <div class="alert-success" style="margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('akuisisi-channel-performance.store') }}">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">

        <section class="card table-card" style="padding:0;">
            <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                <div class="card-title">Performa per Channel</div>
                <div class="card-hint">{{ \Carbon\Carbon::create($tahun, $bulan, 1)->translatedFormat('F Y') }}</div>
            </div>
            <div class="table-scroll" style="max-height:none; overflow-y:visible;">
                <table>
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Lead</th>
                            <th>Prospect</th>
                            <th>Closing</th>
                            <th>Biaya (Rp)</th>
                            <th>Investasi Mitra (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $d = $row['data']; @endphp
                            <tr>
                                <td style="font-weight:600;">{{ $row['channel']->name }}</td>
                                <td><input type="number" min="0" step="1" name="data[{{ $row['channel']->id }}][lead]" value="{{ old('data.'.$row['channel']->id.'.lead', $d->lead ?? 0) }}" {{ $canEdit ? '' : 'disabled' }} style="width:90px;"></td>
                                <td><input type="number" min="0" step="1" name="data[{{ $row['channel']->id }}][prospect]" value="{{ old('data.'.$row['channel']->id.'.prospect', $d->prospect ?? 0) }}" {{ $canEdit ? '' : 'disabled' }} style="width:90px;"></td>
                                <td><input type="number" min="0" step="1" name="data[{{ $row['channel']->id }}][closing]" value="{{ old('data.'.$row['channel']->id.'.closing', $d->closing ?? 0) }}" {{ $canEdit ? '' : 'disabled' }} style="width:90px;"></td>
                                <td><input type="number" min="0" step="0.01" name="data[{{ $row['channel']->id }}][biaya]" value="{{ old('data.'.$row['channel']->id.'.biaya', $d->biaya ?? 0) }}" {{ $canEdit ? '' : 'disabled' }} style="width:140px;"></td>
                                <td><input type="number" min="0" step="0.01" name="data[{{ $row['channel']->id }}][investasi_mitra]" value="{{ old('data.'.$row['channel']->id.'.investasi_mitra', $d->investasi_mitra ?? 0) }}" {{ $canEdit ? '' : 'disabled' }} style="width:140px;"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($canEdit)
                <div style="padding:16px 20px; border-top:1px solid var(--line);">
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                </div>
            @endif
        </section>
    </form>
@endsection
