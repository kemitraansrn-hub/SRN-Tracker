@extends('layouts.guest')

@section('content')
    <div class="guest-title display">Masuk</div>
    <div class="guest-sub">Login untuk mengakses dashboard monitoring mitra.</div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Masuk</button>
    </form>
@endsection
