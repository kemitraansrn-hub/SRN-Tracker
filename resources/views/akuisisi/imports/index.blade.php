@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h1 class="display" style="font-size:24px;">Riwayat Import</h1>
        <a href="{{ route('akuisisi-leads.imports.create') }}" class="btn btn-primary" style="width:auto;">+ Import Lead</a>
    </div>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll" style="max-height:none;">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th>File</th>
                        <th>Oleh</th>
                        <th style="text-align:right;">Berhasil</th>
                        <th style="text-align:right;">Gagal / dilewati</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td>{{ $batch->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $batch->type === 'migration' ? 'Migrasi data lama' : 'Import lead' }}</td>
                            <td style="font-weight:600;">{{ $batch->file_name }}</td>
                            <td>{{ $batch->creator?->name ?? 'Sistem' }}</td>
                            <td style="text-align:right; font-weight:700; color:var(--good);">{{ number_format($batch->success_rows, 0, ',', '.') }}</td>
                            <td style="text-align:right; {{ $batch->failed_rows ? 'font-weight:700; color:var(--critical);' : 'color:var(--ink-faint);' }}">{{ number_format($batch->failed_rows, 0, ',', '.') }}</td>
                            <td style="text-align:right;"><a href="{{ route('akuisisi-leads.imports.show', $batch) }}">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center; color:var(--ink-muted); padding:40px 16px;">Belum ada import.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $batches->links() }}</div>
@endsection
