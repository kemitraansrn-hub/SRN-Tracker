@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <a href="{{ route('akuisisi-leads.index') }}" style="font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Kembali ke Database Lead</a>
        <a href="{{ route('akuisisi-leads.imports.index') }}" class="btn" style="width:auto;">Riwayat Import</a>
    </div>

    <section class="card" style="max-width:720px;">
        <h1 class="display" style="font-size:20px;">Import Lead</h1>
        <div class="card-hint" style="margin-bottom:20px;">Tambahkan banyak calon mitra sekaligus dari file Excel/CSV, atau migrasikan data lead lama yang statusnya sudah berjalan.</div>

        @error('file') <div class="alert-success" style="background:var(--critical-soft); color:var(--critical); margin-bottom:16px;">{{ $message }}</div> @enderror

        <div style="display:flex; gap:12px; margin-bottom:20px;">
            <span style="flex-shrink:0; width:28px; height:28px; border-radius:50%; background:var(--accent-soft); color:var(--accent-ink); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700;">1</span>
            <div>
                <div style="font-weight:600; font-size:13.5px;">Unduh template &amp; isi data</div>
                <div style="font-size:12px; color:var(--ink-muted); margin-top:2px;">Isi sheet <strong>Data Lead</strong> mulai baris ke-2. Kolom pilihan memakai dropdown; petunjuk lengkap ada di sheet Petunjuk (termasuk cara isi migrasi lead lama).</div>
                <a href="{{ route('akuisisi-leads.imports.template') }}" class="btn" style="width:auto; margin-top:10px;">Unduh Template (.xlsx)</a>
            </div>
        </div>

        <div style="display:flex; gap:12px;">
            <span style="flex-shrink:0; width:28px; height:28px; border-radius:50%; background:var(--accent-soft); color:var(--accent-ink); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700;">2</span>
            <form method="POST" action="{{ route('akuisisi-leads.imports.upload') }}" enctype="multipart/form-data" style="flex:1;">
                @csrf
                <label style="font-weight:600; font-size:13.5px; display:block;">Upload file</label>
                <div style="font-size:12px; color:var(--ink-muted); margin-top:2px;">Format .xlsx atau .csv, maksimal 10.000 baris / 10 MB. Data belum disimpan sampai Anda mengonfirmasi di halaman pratinjau.</div>
                <input type="file" name="file" accept=".xlsx,.csv" required style="margin-top:10px; display:block;">

                <label style="display:flex; align-items:flex-start; gap:8px; border:1px solid var(--warn); background:var(--warn-soft); border-radius:8px; padding:8px 12px; margin-top:12px; font-size:11.5px; color:var(--warn);">
                    <input type="checkbox" name="overwrite_existing" value="1" style="margin-top:2px;" {{ old('overwrite_existing') ? 'checked' : '' }}>
                    <span>Timpa data lead yang nomor WA-nya sudah ada di database -- <strong>seluruh kolom diganti</strong> dengan isi file ini, termasuk lead yang sudah ada riwayat follow-up-nya (Tahapan Pipeline, Hasil Follow Up, Tanggal Next Follow Up, Skor &amp; Prioritas ikut diganti sesuai isi file). Pastikan file ini datanya terbaru sebelum upload -- lead yang gak dicentang di sini (nomor WA-nya sudah ada tapi filenya gak ditandai timpa) akan dilewati, gak diubah sama sekali.</span>
                </label>

                <button type="submit" class="btn btn-primary" style="width:auto; margin-top:12px;">Upload &amp; Pratinjau</button>
            </form>
        </div>

        <div style="background:var(--surface-alt); border-radius:8px; padding:14px 16px; margin-top:24px; font-size:12px; color:var(--ink-muted);">
            <div style="font-weight:600; color:var(--ink); margin-bottom:6px;">Yang terjadi saat import</div>
            <ul style="margin:0; padding-left:18px; line-height:1.7;">
                <li>Skor &amp; prioritas dihitung otomatis dari status bisnis, respon/minat, dan modal.</li>
                <li>Lead BARU (kolom Hasil Follow Up &amp; Tanggal Next Follow Up kosong) dijadwalkan follow-up di tanggal masuknya, langsung muncul di To-Do.</li>
                <li>Lead LAMA (salah satu dari 2 kolom itu diisi) ditandai data lama -- status &amp; jadwalnya dipakai apa adanya, tidak dihitung ulang otomatis.</li>
                <li>Nomor WhatsApp yang sudah ada di database dilewati (kecuali centang "Timpa data lead lama" di atas, dan lead-nya memang data lama yang belum pernah di-follow-up); nomor dobel di file hanya diambil baris pertamanya.</li>
                <li>Baris error tidak disimpan dan bisa diunduh sebagai laporan untuk diperbaiki.</li>
            </ul>
        </div>
    </section>
@endsection
