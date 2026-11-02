@extends('layouts.app')

@section('content')
    <a href="{{ route('akuisisi-leads.show', $followup->lead) }}" style="display:inline-block; margin-bottom:12px; font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Kembali ke {{ $followup->lead->display_name }}</a>

    <section class="card" style="max-width:720px;">
        <h1 class="display" style="font-size:20px;">Edit Follow-up</h1>
        <div class="card-hint" style="margin-bottom:20px;">{{ $followup->lead->display_name }}</div>

        <form method="POST" action="{{ route('akuisisi-leads.followups.update', $followup) }}">
            @csrf
            @method('PUT')

            @include('akuisisi.followups._form', ['lead' => $followup->lead])

            <div style="display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--line); padding-top:16px; margin-top:8px;">
                <a href="{{ route('akuisisi-leads.show', $followup->lead) }}" class="btn" style="width:auto;">Batal</a>
                <button type="submit" class="btn btn-primary" style="width:auto;">Simpan Perubahan</button>
            </div>
        </form>
    </section>
@endsection
