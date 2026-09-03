@extends('layouts.app')

@section('content')
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Backup Data</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Database + file upload. Otomatis jalan tiap hari lewat jadwal Windows &mdash; tombol di bawah buat backup manual kapan saja.
            </div>
        </div>
        <form method="POST" action="{{ route('backup.run') }}" onsubmit="
            this.querySelector('button').disabled = true;
            this.querySelector('button').textContent = 'Sedang backup...';
        ">
            @csrf
            <button type="submit" class="btn btn-primary" style="width:auto;">Backup Sekarang</button>
        </form>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Riwayat Backup</div>
            <div class="card-hint">{{ count($backups) }} file &mdash; disimpan 30 hari terakhir, lebih lama otomatis dihapus</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Waktu</th><th>Jenis</th><th>Ukuran</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($backups as $b)
                        <tr>
                            <td class="tnum">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['modified'])->translatedFormat('d M Y, H:i') }}</td>
                            <td>{{ $b['type'] }}</td>
                            <td class="tnum">{{ number_format($b['size'] / 1024 / 1024, 2, ',', '.') }} MB</td>
                            <td>
                                <a href="{{ route('backup.download', $b['name']) }}" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px; text-decoration:none;">Download</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="color:var(--ink-muted);">Belum ada backup. Klik "Backup Sekarang" atau tunggu jadwal otomatis.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
