{{--
    Filter Bulan + Tahun yang reusable, auto-submit tiap ganti pilihan.
    Props:
      $action        (string, required) — route/URL tujuan form (GET)
      $bulan         (int, required)    — bulan terpilih (1-12)
      $tahun         (int, required)    — tahun terpilih
      $isBulanIni    (bool, optional)   — tampilkan tombol "Bulan Ini" kalau bukan bulan berjalan
      $resetAction   (string, optional) — route buat tombol "Bulan Ini" (default: $action)
--}}
@php
    $tahunMulai = $tahunMulai ?? now()->year - 3;
    $tahunSelesai = $tahunSelesai ?? now()->year + 1;
@endphp
<form method="GET" action="{{ $action }}" class="field-row" style="align-items:flex-end;">
    <div class="field" style="margin-bottom:0;">
        <label>Bulan</label>
        <select class="select-pill" name="bulan" onchange="this.form.submit()">
            @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nama)
                <option value="{{ $i + 1 }}" {{ (int) $bulan === $i + 1 ? 'selected' : '' }}>{{ $nama }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="margin-bottom:0;">
        <label>Tahun</label>
        <select class="select-pill" name="tahun" onchange="this.form.submit()">
            @for ($y = $tahunSelesai; $y >= $tahunMulai; $y--)
                <option value="{{ $y }}" {{ (int) $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
    </div>
    @if (isset($isBulanIni) && ! $isBulanIni)
        <div class="field" style="margin-bottom:0;">
            <a href="{{ $resetAction ?? $action }}" class="btn" style="width:auto; font-size:12.5px; padding:8px 14px; text-decoration:none; display:inline-flex; align-items:center;">Bulan Ini</a>
        </div>
    @endif
</form>
