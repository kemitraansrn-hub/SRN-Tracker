@extends('layouts.guest')

@section('content')
    <div class="guest-title display">Masuk</div>
    <div class="guest-sub">Selamat datang kembali! Masuk untuk mengakses dashboard mitra.</div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="guest-field">
            <label for="email">Email</label>
            <div class="guest-input-wrap">
                <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" opacity="0"/><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus>
            </div>
        </div>

        <div class="guest-field">
            <label for="password">Password</label>
            <div class="guest-input-wrap">
                <svg class="leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                <button type="button" class="toggle-eye" onclick="
                    const i = document.getElementById('password');
                    i.type = i.type === 'password' ? 'text' : 'password';
                ">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
        </div>

        <div class="guest-remember-row">
            <label>
                <input type="checkbox" name="remember" value="1">
                Ingat saya
            </label>
            <span style="color:var(--ink-faint);">Lupa password? Hubungi admin.</span>
        </div>

        <button type="submit" class="guest-submit">Masuk</button>
    </form>

    <div class="guest-footnote">SRN Kemitraan &copy; {{ now()->year }}</div>
@endsection
