@extends('layouts.app')

@section('content')
    <a href="{{ route('growth-specialist.profiling-mitra') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali ke Tabel
    </a>

    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Input Mitra</h1>
    <div class="card-hint" style="margin-bottom:20px;">Pilih mitra buat diisi/diubah profilnya.</div>

    <div class="card" style="max-width:480px;">
        <div class="field">
            <label>Pilih Mitra</label>
            @include('partials.searchable-select', [
                'id' => 'pilihMitra',
                'name' => '_picker',
                'options' => $mitraOptions->map(fn ($m) => (object) ['id' => $m->id, 'label' => $m->nama.' ('.$m->kode_mitra.')', 'url' => route('growth-specialist.profiling-mitra.edit', $m)]),
                'placeholder' => 'Ketik buat cari mitra...',
                'extraAttr' => 'url',
                'onchangeJs' => 'gotoPilihanMitra',
            ])
        </div>

        <script>
            function gotoPilihanMitra(id, url) {
                if (url) window.location.href = url;
            }
        </script>
    </div>
@endsection
