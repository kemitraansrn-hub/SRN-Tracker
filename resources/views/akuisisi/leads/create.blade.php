@extends('layouts.app')

@section('content')
    <a href="{{ route('akuisisi-leads.index') }}" style="display:inline-block; margin-bottom:12px; font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Kembali ke daftar</a>

    @if (session('restorable_lead'))
        <div class="alert-success" style="margin-bottom:16px; background:var(--warn-soft); color:var(--warn);">
            Nomor ini milik <strong>{{ session('restorable_lead.name') }}</strong> yang sudah dihapus.
            <form method="POST" action="{{ route('akuisisi-leads.restore', session('restorable_lead.id')) }}" style="display:inline-block; margin-left:8px;">
                @csrf
                <button type="submit" class="btn" style="width:auto; font-size:11.5px; padding:4px 10px;">Pulihkan lead</button>
            </form>
        </div>
    @endif

    <section class="card" style="max-width:780px;">
        <h1 class="display" style="font-size:20px; margin-bottom:20px;">Tambah Mitra Baru</h1>

        <form method="POST" action="{{ route('akuisisi-leads.store') }}">
            @csrf

            @include('akuisisi.leads._form')

            <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--line); padding-top:16px; margin-top:8px;">
                <a href="{{ route('akuisisi-leads.index') }}" class="btn" style="width:auto;">Batal</a>
                <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
            </div>
        </form>
    </section>
@endsection
