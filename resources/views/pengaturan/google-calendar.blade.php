@extends('layouts.app')

@section('content')
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Google Calendar</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Jadwal Zoom di menu Assignment otomatis dibikinkan event Google Calendar kalau sudah connect di sini.
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section class="card" style="max-width:560px;">
        @if (! $isConfigured)
            <div style="font-size:13px; color:var(--ink-muted); line-height:1.6;">
                Client ID/Client Secret Google belum diisi di <code>.env</code> (<code>GOOGLE_CALENDAR_CLIENT_ID</code>, <code>GOOGLE_CALENDAR_CLIENT_SECRET</code>, <code>GOOGLE_CALENDAR_REDIRECT_URI</code>). Minta tim developer isi dulu sebelum bisa connect.
            </div>
        @elseif ($isConnected)
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                <span class="chip chip-good">Connected</span>
                <span style="font-size:13px; color:var(--ink);">{{ $connectedEmail ?? 'Akun Google tersambung' }}</span>
            </div>
            <div style="font-size:12.5px; color:var(--ink-muted); margin-bottom:16px; line-height:1.6;">
                Semua jadwal Zoom baru/diubah/dihapus di menu Assignment akan otomatis tersinkron ke kalender akun ini. KAE yang terkait mitra-nya ikut diundang sebagai attendee (lewat email undangan dari Google).
            </div>
            <form method="POST" action="{{ route('google-calendar.disconnect') }}" onsubmit="return confirm('Disconnect Google Calendar? Jadwal Zoom baru tidak akan tersinkron sampai di-connect lagi. Event yang sudah ada di kalender TIDAK ikut terhapus.');">
                @csrf
                <button type="submit" class="btn btn-danger" style="width:auto;">Disconnect</button>
            </form>
        @else
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                <span class="chip chip-critical">Belum Connect</span>
            </div>
            <div style="font-size:12.5px; color:var(--ink-muted); margin-bottom:16px; line-height:1.6;">
                Klik tombol di bawah, login pakai akun Google yang jadwal Zoom-nya mau dipakai (mis. kemitraan.srn@gmail.com), lalu izinkan akses ke Google Calendar.
            </div>
            <a href="{{ route('google-calendar.connect') }}" class="btn btn-primary" style="width:auto; text-decoration:none; display:inline-block;">Connect Google Calendar</a>
        @endif
    </section>
@endsection
