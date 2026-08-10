@extends('layouts.app')

@section('content')
    <a href="{{ route('users.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $targetUser->exists ? 'Edit User' : 'Tambah User Baru' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $targetUser->exists ? route('users.update', $targetUser) : route('users.store') }}" class="card" style="max-width:560px;">
        @csrf
        @if ($targetUser->exists) @method('PUT') @endif

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Nama</label>
                <input type="text" name="name" value="{{ old('name', $targetUser->name) }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $targetUser->email) }}" required>
            </div>
        </div>

        <div class="field">
            <label>{{ $targetUser->exists ? 'Password Baru (kosongkan kalau tidak diubah)' : 'Password' }}</label>
            <input type="password" name="password" {{ $targetUser->exists ? '' : 'required' }} minlength="6">
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Role</label>
                <select name="role" id="roleSelect" onchange="toggleKaeCode()">
                    <option value="kae" {{ old('role', $targetUser->role ?? 'kae') === 'kae' ? 'selected' : '' }}>KAE</option>
                    <option value="admin" {{ old('role', $targetUser->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>
            <div class="field" style="flex:1;" id="kaeCodeField">
                <label>Kode KAE</label>
                <input type="text" name="kae_code" value="{{ old('kae_code', $targetUser->kae_code) }}" maxlength="5" placeholder="mis. B">
            </div>
        </div>

        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="aktif" {{ old('status', $targetUser->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ old('status', $targetUser->status) === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $targetUser->exists ? 'Simpan Perubahan' : 'Tambah User' }}</button>
    </form>

    <script>
        function toggleKaeCode() {
            const isAdmin = document.getElementById('roleSelect').value === 'admin';
            document.getElementById('kaeCodeField').style.display = isAdmin ? 'none' : '';
        }
        toggleKaeCode();
    </script>
@endsection
