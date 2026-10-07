@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Stand in Line</h1>
    <div class="card-hint" style="margin-bottom:16px;">Mitra yang LMS-nya sudah Lengkap di minimal satu platform, tapi belum tentu upload laporan Tracking Performance mingguannya. Jumlah kolom minggu mengikuti data yang di-upload (otomatis bertambah kalau ada yang sampai W12, dst). Centang di layar kumulatif (pernah upload minggu itu kapan pun). Download Excel men-snapshot satu KUARTAL PENUH (3 bulan) yang memuat Bulan terpilih — bukan cuma bulan itu sendiri, karena penomoran minggu (W1, W2, dst) reset tiap kuartal.</div>

    <div style="display:flex; gap:4px; margin-bottom:22px; border-bottom:1px solid var(--line);">
        <a href="{{ route('growth-specialist.tracking-performance') }}" style="padding:10px 4px; margin-right:22px; text-decoration:none; border-bottom:2px solid transparent; font-size:14px; font-weight:600; color:var(--ink-muted);">Tracking Performance</a>
        <a href="{{ route('growth-specialist.tracking-performance.stand-in-line') }}" style="padding:10px 4px; margin-right:22px; text-decoration:none; border-bottom:2px solid var(--accent); font-size:14px; font-weight:700; color:var(--ink);">Stand in Line</a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('growth-specialist.tracking-performance.stand-in-line') }}" class="field-row" style="align-items:flex-end;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:200px;">
            <label>Cari Mitra</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nama atau kode mitra...">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Bulan</label>
            <select class="select-pill" name="bulan" onchange="this.form.submit()">
                @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nama)
                    <option value="{{ $i + 1 }}" {{ $bulan == $i + 1 ? 'selected' : '' }}>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Tahun</label>
            <select class="select-pill" name="tahun" onchange="this.form.submit()">
                @for ($y = now()->year + 1; $y >= now()->year - 2; $y--)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if ($q !== '')
            <a href="{{ route('growth-specialist.tracking-performance.stand-in-line', ['bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
            <div>
                <div class="card-title">Stand in Line</div>
                <div class="card-hint">{{ $rows->count() }} mitra</div>
            </div>
            @if ($rows->isNotEmpty())
                <a href="{{ route('growth-specialist.tracking-performance.stand-in-line.download', ['bulan' => $bulan, 'tahun' => $tahun, 'q' => $q]) }}" class="btn" style="width:auto;">Download Excel</a>
            @endif
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID Mitra</th><th>Nama Mitra</th><th>KAE</th><th>Segmen</th>
                        @foreach ($weeks as $w)
                            <th style="text-align:center;">{{ $w }}</th>
                        @endforeach
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td style="font-size:12px;">{{ $r['kode_mitra'] }}</td>
                            <td><a href="{{ route('mitra.show', $r['mitra_id']) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $r['nama'] }}</a></td>
                            <td>{{ $r['kae'] }}</td>
                            <td>{{ $r['segmen'] }}</td>
                            @foreach ($weeks as $w)
                                <td style="text-align:center;">
                                    @if (in_array($w, $r['weeks'], true))
                                        <span style="color:var(--good); font-weight:700;">&#10003;</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    @if ($r['note'])
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openSilNoteModal('{{ $r['mitra_id'] }}', '{{ addslashes($r['nama']) }}', @js($r['note']->catatan))">Edit Note</button>
                                        <form method="POST" action="{{ route('growth-specialist.tracking-performance.stand-in-line.note.destroy', $r['note']->id) }}" onsubmit="return confirm('Hapus catatan untuk {{ addslashes($r['nama']) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openSilNoteModal('{{ $r['mitra_id'] }}', '{{ addslashes($r['nama']) }}', '')">Note</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 5 + count($weeks) }}" style="color:var(--ink-muted);">Tidak ada mitra yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-overlay" id="modal-sil-note" style="display:none;">
        <div class="modal-box">
            <div class="modal-title" id="sil-note-title">Catatan</div>
            <form method="POST" action="{{ route('growth-specialist.tracking-performance.stand-in-line.note.store') }}">
                @csrf
                <input type="hidden" name="mitra_id" id="sil-note-mitra-id">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <div class="field">
                    <label>Alasan/kendala belum upload laporan</label>
                    <textarea name="catatan" id="sil-note-catatan" rows="4" required></textarea>
                </div>
                <div class="modal-actions" style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn" style="width:auto;" onclick="closeSilNoteModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openSilNoteModal(mitraId, nama, catatan) {
            document.getElementById('sil-note-mitra-id').value = mitraId;
            document.getElementById('sil-note-title').textContent = 'Catatan — ' + nama;
            document.getElementById('sil-note-catatan').value = catatan || '';
            document.getElementById('modal-sil-note').style.display = 'flex';
        }
        function closeSilNoteModal() {
            document.getElementById('modal-sil-note').style.display = 'none';
        }
    </script>
@endsection
