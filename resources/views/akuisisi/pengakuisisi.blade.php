@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Pengakuisisi</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $pengakuisisiList->count() }} pengakuisisi &mdash; data kontak &amp; rekening buat pembayaran bonus Program Akuisisi Mitra Offline
            </div>
        </div>
        @if (auth()->user()->hasAdminAccess())
            <button type="button" class="btn btn-primary" style="width:auto;" onclick="const d = document.getElementById('tambah-pengakuisisi'); d.style.display = d.style.display === 'none' ? '' : 'none';">+ Tambah Pengakuisisi</button>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    @if (auth()->user()->hasAdminAccess())
        <section id="tambah-pengakuisisi" class="card" style="margin-bottom:20px; display:none;">
            <div class="info-label" style="margin-bottom:10px;">Tambah Pengakuisisi</div>
            <form method="POST" action="{{ route('pengakuisisi.store') }}" class="field-row" style="align-items:flex-end;">
                @csrf
                <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                    <label>Nama</label>
                    <input type="text" name="nama" required placeholder="Nama lengkap">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Bank</label>
                    <input type="text" name="bank" placeholder="mis. BCA">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>No Rekening</label>
                    <input type="text" name="no_rekening" placeholder="mis. 1234567890">
                </div>
                <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                    <label>Alamat</label>
                    <input type="text" name="alamat" placeholder="opsional">
                </div>
                <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
            </form>
        </section>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama</th><th>Bank</th><th>No Rekening</th><th>Alamat</th><th>Jumlah Mitra</th>@if (auth()->user()->hasAdminAccess())<th></th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($pengakuisisiList as $p)
                        <tr>
                            <td style="font-weight:600;">{{ $p->nama }}</td>
                            <td>{{ $p->bank ?: '—' }}</td>
                            <td class="tnum">{{ $p->no_rekening ?: '—' }}</td>
                            <td style="max-width:220px; white-space:normal; font-size:12.5px; color:var(--ink-muted);">{{ $p->alamat ?: '—' }}</td>
                            <td class="tnum">{{ $p->akuisisi_referrals_count }}</td>
                            @if (auth()->user()->hasAdminAccess())
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('edit-pengakuisisi-{{ $p->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Edit</button>
                                        <form method="POST" action="{{ route('pengakuisisi.destroy', $p) }}" onsubmit="return confirm('Hapus pengakuisisi {{ addslashes($p->nama) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                        @if (auth()->user()->hasAdminAccess())
                            <tr id="edit-pengakuisisi-{{ $p->id }}" style="display:none;">
                                <td colspan="6" style="background:var(--surface-alt);">
                                    <form method="POST" action="{{ route('pengakuisisi.update', $p) }}" class="field-row" style="align-items:flex-end; padding:10px 4px;">
                                        @csrf
                                        @method('PUT')
                                        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                                            <label>Nama</label>
                                            <input type="text" name="nama" value="{{ $p->nama }}" required>
                                        </div>
                                        <div class="field" style="margin-bottom:0;">
                                            <label>Bank</label>
                                            <input type="text" name="bank" value="{{ $p->bank }}">
                                        </div>
                                        <div class="field" style="margin-bottom:0;">
                                            <label>No Rekening</label>
                                            <input type="text" name="no_rekening" value="{{ $p->no_rekening }}">
                                        </div>
                                        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                                            <label>Alamat</label>
                                            <input type="text" name="alamat" value="{{ $p->alamat }}">
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada Pengakuisisi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
