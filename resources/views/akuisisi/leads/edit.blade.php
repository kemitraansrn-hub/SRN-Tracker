@extends('layouts.app')

@section('content')
    <a href="{{ route('akuisisi-leads.index') }}" style="display:inline-block; margin-bottom:12px; font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Kembali ke daftar</a>

    @if (session('restorable_lead'))
        <div class="alert-success" style="margin-bottom:16px; background:var(--warn-soft); color:var(--warn);">
            Nomor tersebut milik <strong>{{ session('restorable_lead.name') }}</strong> yang sudah dihapus, sehingga tidak bisa dipakai di lead ini.
        </div>
    @endif

    <section class="card" style="max-width:780px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 class="display" style="font-size:20px;">Edit Mitra</h1>
            @if ($lead->is_legacy)
                <span class="chip chip-neutral">Data lama (migrasi)</span>
            @endif
        </div>

        <form method="POST" action="{{ route('akuisisi-leads.update', $lead) }}">
            @csrf
            @method('PUT')

            @include('akuisisi.leads._form')

            <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--line); padding-top:16px; margin-top:8px;">
                <a href="{{ route('akuisisi-leads.index') }}" class="btn" style="width:auto;">Batal</a>
                <button type="submit" class="btn btn-primary" style="width:auto;">Simpan Perubahan</button>
            </div>
        </form>
    </section>

    @if ($lead->legacy_data)
        <details class="card" style="max-width:780px; margin-top:16px;">
            <summary style="cursor:pointer; font-size:13px; font-weight:600;">Data asli Excel (migrasi)</summary>
            <dl style="display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; margin-top:16px; font-size:12.5px;">
                @foreach ($lead->legacy_data as $label => $value)
                    <div style="{{ is_array($value) ? 'grid-column:span 2;' : '' }}">
                        <dt style="font-size:11px; color:var(--ink-muted);">{{ $label }}</dt>
                        <dd style="margin-top:2px; color:var(--ink);">
                            @if (is_array($value))
                                <ul style="padding-left:18px; margin:0;">
                                    @foreach ($value as $item) <li>{{ $item }}</li> @endforeach
                                </ul>
                            @else
                                {{ $value === null || $value === '' ? '–' : $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </details>
    @endif

    <form method="POST" action="{{ route('akuisisi-leads.destroy', $lead) }}" style="max-width:780px; display:flex; justify-content:flex-end; margin-top:16px;"
        onsubmit="return confirm('Hapus lead &quot;{{ addslashes($lead->display_name) }}&quot;?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger" style="width:auto; font-size:12px;">Hapus lead ini</button>
    </form>
@endsection
