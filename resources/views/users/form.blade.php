@extends('layouts.app')

@section('content')
    <a href="{{ route('users.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $targetUser->exists ? 'Edit User' : 'Tambah User Baru' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $targetUser->exists ? route('users.update', $targetUser) : route('users.store') }}" class="card" style="max-width:560px;" enctype="multipart/form-data">
        @csrf
        @if ($targetUser->exists) @method('PUT') @endif

        <div class="field">
            <label>Foto Profil</label>
            <div style="display:flex; align-items:center; gap:14px;">
                @if ($targetUser->photoUrl())
                    <img src="{{ $targetUser->photoUrl() }}" alt="{{ $targetUser->name }}" style="width:56px; height:56px; border-radius:50%; object-fit:cover; flex:none;">
                @else
                    <div style="width:56px; height:56px; border-radius:50%; background:var(--accent-soft); color:var(--accent-ink); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; flex:none;">
                        {{ mb_strtoupper(mb_substr($targetUser->name ?? '?', 0, 1)) }}
                    </div>
                @endif
                <div style="flex:1;">
                    <input type="file" name="photo" accept="image/*">
                    @if ($targetUser->photoUrl())
                        <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:400; color:var(--ink-muted); margin-top:6px;">
                            <input type="checkbox" name="hapus_foto" value="1"> Hapus foto saat ini
                        </label>
                    @endif
                </div>
            </div>
        </div>

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
                    <option value="head" {{ old('role', $targetUser->role) === 'head' ? 'selected' : '' }}>Head of SRN</option>
                    <option value="finance" {{ old('role', $targetUser->role) === 'finance' ? 'selected' : '' }}>Finance</option>
                </select>
            </div>
            <div class="field" style="flex:1;" id="kaeCodeField">
                <label>Kode KAE</label>
                <input type="text" name="kae_code" value="{{ old('kae_code', $targetUser->kae_code) }}" maxlength="5" placeholder="mis. B">
            </div>
        </div>

        <div class="field" id="targetAktifField">
            <label>Target Jumlah Mitra Aktif / Bulan</label>
            <input type="number" name="target_mitra_aktif" value="{{ old('target_mitra_aktif', $targetUser->target_mitra_aktif) }}" min="0" placeholder="mis. 90">
            <div style="font-size:11.5px; color:var(--ink-muted); margin-top:4px;">Dipakai di kartu Run Rate Mitra Active pada Dashboard.</div>
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
            const isKae = document.getElementById('roleSelect').value === 'kae';
            document.getElementById('kaeCodeField').style.display = isKae ? '' : 'none';
            document.getElementById('targetAktifField').style.display = isKae ? '' : 'none';
        }
        toggleKaeCode();
    </script>
@endsection
