@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:20px;">{{ $title }}</h1>

    <div class="card" style="text-align:center; padding:60px 20px; color:var(--ink-muted);">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="margin:0 auto 16px; opacity:0.5;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <div style="font-size:15px; font-weight:600; color:var(--ink); margin-bottom:6px;">Segera Hadir</div>
        <div style="font-size:13px;">Halaman "{{ $title }}" masih dalam tahap perencanaan.</div>
    </div>
@endsection
