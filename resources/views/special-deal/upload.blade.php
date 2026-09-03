@extends('layouts.app')

@section('content')
    <a href="{{ route('special-deal.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:6px;">Upload Special Deal</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-bottom:20px;">
        Upload satu file berisi banyak mitra sekaligus untuk satu kuartal. Mitra yang sudah punya deal di kuartal &amp; tahun yang sama akan diperbarui (bukan dobel), bukan dibuat baru.
    </div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <div class="card" style="max-width:640px;">
        <div style="margin-bottom:18px;">
            <a href="{{ route('special-deal.template') }}" class="btn" style="width:auto;">Download Template</a>
        </div>

        <form method="POST" action="{{ route('special-deal.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label>File Special Deal (.xlsx)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;">Upload</button>
        </form>
    </div>

    <div class="card" style="max-width:640px; margin-top:16px; font-size:12.5px; color:var(--ink-muted);">
        <div style="font-weight:600; color:var(--ink); margin-bottom:8px;">Kolom yang dibaca</div>
        <div>KODE MITRA (wajib, harus sudah terdaftar), NAMA MITRA (info saja), KAE (kode/nama, kosong = KAE Anda), SEGMENTASI (Pareto/RTP/Reguler/Special Reguler), DESKRIPSI, KUARTAL (1-4), TAHUN, TARGET KUARTAL (RP), BUDGET (%), SUBSIDI, STATUS MOU (Proses/Done/Batal, kosong = Proses).</div>
    </div>
@endsection
