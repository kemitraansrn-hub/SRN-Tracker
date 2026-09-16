{{--
    Select yang bisa dicari, tanpa library JS eksternal - pakai <input list>
    + <datalist> browser native, plus hidden input buat nyimpen ID
    beneran yang bakal ke-submit (biar drop-in pengganti <select name=X>
    yang lama, gak perlu ubah controller/validasi).

    Props:
    - name: nama field yang ke-submit (isinya ID)
    - options: iterable, tiap item HARUS punya ->id dan ->label (siapin di
      view pemanggil pakai ->map(), soalnya label sering gabungan beberapa
      kolom kayak "Nama (Kode)" - lihat contoh di cp-case/form.blade.php)
    - selectedId: ID yang lagi kepilih (buat old()/edit)
    - placeholder: placeholder input pencarian
    - extraAttr: nama properti tambahan (di $options) yang mau dibawa
      sebagai data-extra di tiap option (buat kasus kayak produk_id yang
      butuh harga_het buat auto-isi field lain)
    - onchangeJs: nama fungsi JS global yang dipanggil tiap kali user
      milih/ganti pilihan, dikasih 1 argumen: value hidden input-nya
--}}
@php
    $fieldId = $id ?? 'ss_'.$name;
    $selectedOption = null;
    if (isset($selectedId) && $selectedId !== null && $selectedId !== '') {
        foreach ($options as $opt) {
            if ((string) $opt->id === (string) $selectedId) {
                $selectedOption = $opt;
                break;
            }
        }
    }
    $displayValue = $selectedOption->label ?? '';
@endphp
<div class="searchable-select-wrap" data-onchange="{{ $onchangeJs ?? '' }}">
    <input
        type="text"
        class="searchable-select-input"
        id="{{ $fieldId }}_search"
        list="{{ $fieldId }}_list"
        value="{{ old($name.'_label', $displayValue) }}"
        placeholder="{{ $placeholder ?? '— pilih —' }}"
        autocomplete="off"
    >
    <input type="hidden" name="{{ $name }}" id="{{ $fieldId }}_hidden" value="{{ old($name, $selectedId ?? '') }}">
    <datalist id="{{ $fieldId }}_list">
        @foreach ($options as $opt)
            <option data-id="{{ $opt->id }}" @if (! empty($extraAttr)) data-extra="{{ $opt->{$extraAttr} }}" @endif value="{{ $opt->label }}"></option>
        @endforeach
    </datalist>
</div>
