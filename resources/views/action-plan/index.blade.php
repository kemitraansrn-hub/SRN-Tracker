@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Action Plan</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Slide rencana aksi tim, format PDF.
            </div>
        </div>
        @if (auth()->user()->hasAdminAccess())
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-primary" style="width:auto;" onclick="const d = document.getElementById('upload-action-plan'); d.style.display = d.style.display === 'none' ? '' : 'none';">
                    {{ $actionPlan ? 'Ganti File' : '+ Upload Action Plan' }}
                </button>
                @if ($actionPlan)
                    <form method="POST" action="{{ route('action-plan.destroy', $actionPlan) }}" onsubmit="return confirm('Hapus Action Plan ini? File-nya bakal hilang permanen.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="width:auto;">Hapus</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    @if (auth()->user()->hasAdminAccess())
        <section id="upload-action-plan" class="card" style="margin-bottom:20px; display:none;">
            <div class="info-label" style="margin-bottom:10px;">{{ $actionPlan ? 'Upload File Baru (gantiin yang lama)' : 'Upload Action Plan' }}</div>
            <form method="POST" action="{{ route('action-plan.store') }}" enctype="multipart/form-data" class="field-row" style="align-items:flex-end;">
                @csrf
                <div class="field" style="margin-bottom:0; flex:1; min-width:220px;">
                    <label>File PDF (maks 20MB)</label>
                    <input type="file" name="file" accept="application/pdf" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:auto;">Upload</button>
            </form>
        </section>
    @endif

    @if ($actionPlan)
        <div style="color:var(--ink-muted); font-size:12px; margin-bottom:10px;">
            {{ $actionPlan->nama_file }} &middot; diupload oleh {{ $actionPlan->uploader->name ?? '—' }} &middot; {{ $actionPlan->created_at->translatedFormat('d F Y, H:i') }}
        </div>
        <div class="card" style="padding:0; overflow:hidden;">
            <iframe src="{{ route('action-plan.show') }}" style="width:100%; height:85vh; border:none; display:block;"></iframe>
        </div>
    @else
        <div class="card" style="color:var(--ink-muted); text-align:center; padding:48px 20px;">
            Belum ada Action Plan yang diupload.
        </div>
    @endif
@endsection
