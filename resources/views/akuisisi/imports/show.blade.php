@extends('layouts.app')

@section('content')
    <a href="{{ route('akuisisi-leads.imports.index') }}" style="display:inline-block; margin-bottom:12px; font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Riwayat Import</a>

    <section class="card" style="max-width:720px;">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:10px;">
            <div>
                <h1 class="display" style="font-size:20px;">Hasil Import #{{ $batch->id }}</h1>
                <div class="card-hint" style="margin-top:4px;">
                    {{ $batch->file_name }} &middot; {{ $batch->created_at->translatedFormat('d M Y H:i') }}
                    @if ($batch->creator) &middot; oleh {{ $batch->creator->name }} @endif
                </div>
            </div>
            <span class="chip chip-neutral">{{ $batch->type === 'migration' ? 'Migrasi data lama' : 'Import lead' }}</span>
        </div>

        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-top:20px;">
            @foreach ([
                ['Baris di file', $batch->total_rows, 'var(--ink)'],
                ['Berhasil', $batch->success_rows, 'var(--good)'],
                ['Gagal / dilewati', $batch->failed_rows, 'var(--critical)'],
            ] as [$label, $value, $color])
                <div class="card" style="padding:12px 14px;">
                    <div style="font-size:11px; color:var(--ink-muted); font-weight:600;">{{ $label }}</div>
                    <div class="tnum" style="font-size:19px; font-weight:700; color:{{ $color }}; margin-top:3px;">{{ number_format($value, 0, ',', '.') }}</div>
                </div>
            @endforeach
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:20px;">
            @if ($batch->type === 'import')
                <a href="{{ route('akuisisi-leads.index', ['tab' => 'todo']) }}" class="btn btn-primary" style="width:auto;">Buka To-Do</a>
            @endif
            @if ($batch->error_report)
                <a href="{{ route('akuisisi-leads.imports.errors', $batch) }}" class="btn" style="width:auto;">Unduh Laporan Error</a>
            @endif
            <a href="{{ route('akuisisi-leads.imports.create') }}" class="btn" style="width:auto;">Import file lain</a>
        </div>
    </section>
@endsection
