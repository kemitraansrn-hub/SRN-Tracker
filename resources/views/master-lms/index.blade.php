@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Master LMS</h1>
    <div class="card-hint" style="margin-bottom:16px;">Kelola daftar video LMS per platform yang dipakai menu Set Up LMS. Nomor urut dan judul langsung tampil di checklist dan tabel progres mitra.</div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex; gap:4px; margin-bottom:22px; border-bottom:1px solid var(--line);">
        @foreach ($platforms as $key => $label)
            <a href="{{ route('master-lms.index', ['tab' => $key]) }}" style="padding:10px 4px; margin-right:22px; text-decoration:none; border-bottom:2px solid {{ $tab === $key ? 'var(--accent)' : 'transparent' }}; font-size:14px; font-weight:{{ $tab === $key ? '700' : '600' }}; color:var(--{{ $tab === $key ? 'ink' : 'ink-muted' }});">{{ $label }}</a>
        @endforeach
    </div>

    <section class="card" style="max-width:820px; margin-bottom:20px;">
        <div class="card-title" style="margin-bottom:12px;">Tambah Video {{ $platforms[$tab] }}</div>
        <form method="POST" action="{{ route('master-lms.store') }}" class="field-row" style="align-items:flex-end; margin-bottom:0;">
            @csrf
            <input type="hidden" name="platform" value="{{ $tab }}">
            <div class="field" style="margin-bottom:0; width:90px;">
                <label>Urutan</label>
                <input type="number" name="urutan" min="1" max="999" value="{{ $nextUrutan }}">
            </div>
            <div class="field" style="margin-bottom:0; flex:1; min-width:240px;">
                <label>Judul Video</label>
                <input type="text" name="judul" maxlength="255" required placeholder="Contoh: Tutorial Voucher Toko">
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;">+ Tambah</button>
        </form>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Daftar Video {{ $platforms[$tab] }}</div>
            <div class="card-hint">{{ $steps->where('aktif', true)->count() }} aktif dari {{ $steps->count() }} video</div>
        </div>
        <div class="card-hint" style="padding:0 20px 12px;">
            % Selesai mitra dihitung dari video yang <strong>aktif</strong>. Menambah atau menonaktifkan video akan langsung mengubah % dan status (Awal/Proses/Lengkap) semua mitra.
        </div>
        <div class="table-scroll" style="max-height:none; overflow-y:visible;">
            <table>
                <thead>
                    <tr><th style="width:90px;">Urutan</th><th>Judul Video</th><th>Aktif</th><th>Mitra Sudah Centang</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($steps as $step)
                        <tr style="{{ $step->aktif ? '' : 'opacity:.55;' }}">
                            <td><input type="number" name="urutan" form="lmsUpdate{{ $step->id }}" value="{{ $step->urutan }}" min="1" max="999" required style="width:72px;"></td>
                            <td><input type="text" name="judul" form="lmsUpdate{{ $step->id }}" value="{{ $step->judul }}" maxlength="255" required style="min-width:280px;"></td>
                            <td>
                                <input type="hidden" name="aktif" form="lmsUpdate{{ $step->id }}" value="0">
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                                    <input type="checkbox" name="aktif" form="lmsUpdate{{ $step->id }}" value="1" {{ $step->aktif ? 'checked' : '' }}>
                                    {{ $step->aktif ? 'Aktif' : 'Nonaktif' }}
                                </label>
                            </td>
                            <td class="tnum">{{ $step->completions_count }} mitra</td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <button type="submit" form="lmsUpdate{{ $step->id }}" class="btn btn-primary" style="width:auto; font-size:12px; padding:5px 12px;">Simpan</button>
                                    <button type="submit" form="lmsDelete{{ $step->id }}" class="btn btn-danger" style="width:auto; font-size:12px; padding:5px 12px;">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--ink-muted);">Belum ada video {{ $platforms[$tab] }}. Tambahkan lewat form di atas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($steps as $step)
        <form method="POST" action="{{ route('master-lms.update', $step) }}" id="lmsUpdate{{ $step->id }}">
            @csrf
            @method('PUT')
        </form>
        <form method="POST" action="{{ route('master-lms.destroy', $step) }}" id="lmsDelete{{ $step->id }}" onsubmit="return confirm('Hapus video &quot;{{ addslashes($step->judul) }}&quot;?{{ $step->completions_count > 0 ? ' Video ini sudah dicentang '.$step->completions_count.' mitra, jadi tidak akan bisa dihapus (nonaktifkan saja).' : '' }}');">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endsection
