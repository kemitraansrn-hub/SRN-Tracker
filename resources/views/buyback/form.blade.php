@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');

    $existingItems = $buybackRequest
        ? $buybackRequest->items->map(fn ($i) => [
            'produk_id' => $i->produk_id,
            'nama_produk' => $i->nama_produk,
            'harga' => (float) $i->harga,
            'qty' => $i->qty,
            'tanggal_ed' => $i->tanggal_ed->toDateString(),
            'umur_produk_bulan' => $i->umur_produk_bulan,
        ])->values()
        : collect();
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">{{ $buybackRequest ? 'Edit Pengajuan Buy Back' : 'Pengajuan Buy Back Baru' }}</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Nilai Buy Back per produk dihitung otomatis: Subtotal &times; (1 &minus; Tingkat Penyusutan)<sup>Umur Produk (bulan)</sup>.
    </div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $buybackRequest ? route('buyback.update', $buybackRequest) : route('buyback.store') }}" id="buyback-form">
        @csrf
        @if ($buybackRequest)
            @method('PUT')
        @endif

        <section class="card" style="margin-bottom:20px;">
            <div class="field-row" style="align-items:flex-end;">
                <div class="field" style="margin-bottom:0; flex:1; min-width:220px;">
                    <label>Nama Mitra</label>
                    <select id="mitra-select" name="mitra_id" required>
                        <option value="">-- pilih mitra --</option>
                        @foreach ($mitraList as $m)
                            <option value="{{ $m->id }}" data-aov="{{ $m->aov }}" {{ (old('mitra_id', $buybackRequest->mitra_id ?? '')) == $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>AOV (rata-rata order YTD)</label>
                    <input type="text" id="mitra-aov" readonly style="background:var(--surface-alt); color:var(--ink-muted);" value="{{ $buybackRequest ? $rp($buybackRequest->aov) : '' }}">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Tingkat Penyusutan (%)</label>
                    <input type="text" id="tingkat_penyusutan_display" readonly style="background:var(--surface-alt); color:var(--ink-muted);" value="{{ rtrim(rtrim(number_format($currentRate, 2, ',', '.'), '0'), ',') }}%">
                    <div style="font-size:11px; color:var(--ink-faint); margin-top:4px;">Dikunci admin di <a href="{{ route('buyback-setting.edit') }}" style="color:inherit;">Pengaturan</a>.</div>
                </div>
            </div>
        </section>

        <section class="card table-card" style="margin-bottom:20px;">
            <div class="card-head" style="margin-bottom:14px;">
                <div class="card-title">Produk<span style="color:var(--critical);">*</span></div>
                <button type="button" class="btn btn-primary" style="width:auto;" onclick="openProdukModal()">+ Tambah Produk</button>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Nilai Beli</th><th>ED</th><th>Umur (bulan)</th><th>Nilai Penyusutan</th><th>Nilai Buy Back</th><th></th></tr>
                    </thead>
                    <tbody id="items-tbody"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right; font-weight:700;">Total:</td>
                            <td class="tnum" style="font-weight:700;" id="total-beli">Rp0</td>
                            <td colspan="2"></td>
                            <td class="tnum" style="font-weight:700;" id="total-penyusutan">Rp0</td>
                            <td class="tnum" style="font-weight:700;" id="grand-total">Rp0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div id="items-empty" style="color:var(--ink-muted); font-size:13px; padding:14px 4px;">Belum ada produk dipilih.</div>
        </section>

        <div id="hidden-items"></div>

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
            <a href="{{ route('buyback.index') }}" class="btn" style="width:auto;">Batal</a>
        </div>
    </form>

    <div id="produk-modal" style="display:none; position:fixed; inset:0; background:rgba(27,34,44,0.45); z-index:100; align-items:flex-start; justify-content:center; padding:40px 20px;">
        <div class="card" style="max-width:720px; width:100%; max-height:80vh; display:flex; flex-direction:column; padding:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:18px 20px; border-bottom:1px solid var(--line);">
                <div class="card-title" style="margin:0;">Pilih Produk</div>
                <button type="button" onclick="closeProdukModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--ink-muted); line-height:1;">&times;</button>
            </div>
            <div style="padding:14px 20px;">
                <input type="text" id="produk-search" placeholder="Cari produk..." oninput="renderProdukOptions()" style="width:100%;">
            </div>
            <div class="table-scroll" style="flex:1; overflow-y:auto; padding:0 20px;">
                <table>
                    <thead><tr><th>Nama</th><th>Brand</th><th>Harga</th><th></th></tr></thead>
                    <tbody id="produk-options"></tbody>
                </table>
            </div>
            <div style="padding:14px 20px; border-top:1px solid var(--line); text-align:right;">
                <button type="button" class="btn btn-primary" style="width:auto;" onclick="closeProdukModal()">Selesai</button>
            </div>
        </div>
    </div>

    <script>
        const produkList = @json($produkList->values());
        const rp = (v) => 'Rp' + Math.round(v || 0).toLocaleString('id-ID');
        const todayStr = @json(now()->toDateString());
        let items = @json($existingItems);

        document.getElementById('mitra-select').addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            document.getElementById('mitra-aov').value = opt && opt.value ? rp(parseFloat(opt.dataset.aov || 0)) : '';
        });

        function openProdukModal() {
            document.getElementById('produk-search').value = '';
            renderProdukOptions();
            document.getElementById('produk-modal').style.display = 'flex';
        }
        function closeProdukModal() {
            document.getElementById('produk-modal').style.display = 'none';
        }

        function renderProdukOptions() {
            const q = document.getElementById('produk-search').value.toLowerCase();
            const tbody = document.getElementById('produk-options');
            tbody.innerHTML = '';
            produkList
                .filter(p => p.nama.toLowerCase().includes(q) || (p.kode_sku || '').toLowerCase().includes(q))
                .forEach(p => {
                    const checked = items.some(i => i.produk_id === p.id);
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${p.kode_sku ? '<span style="color:var(--ink-faint); font-size:11.5px;">' + p.kode_sku + '</span><br>' : ''}${p.nama}</td>
                        <td>${p.brand}</td>
                        <td class="tnum">${rp(p.harga)}</td>
                        <td><input type="checkbox" ${checked ? 'checked' : ''} onchange="toggleProduk(${p.id}, this.checked)"></td>
                    `;
                    tbody.appendChild(tr);
                });
        }

        function toggleProduk(produkId, checked) {
            const p = produkList.find(x => x.id === produkId);
            if (checked) {
                if (!items.some(i => i.produk_id === produkId)) {
                    items.push({produk_id: p.id, nama_produk: p.nama, harga: p.harga || 0, qty: 1, tanggal_ed: todayStr, umur_produk_bulan: 0});
                }
            } else {
                items = items.filter(i => i.produk_id !== produkId);
            }
            renderItems();
        }

        function removeItem(index) {
            items.splice(index, 1);
            renderItems();
            renderProdukOptions();
        }

        function updateItem(idx, field, value) {
            items[idx][field] = (field === 'tanggal_ed') ? value : (parseFloat(value) || 0);
            renderItems();
        }

        const tingkatPenyusutan = @json($currentRate);

        function nilaiBuyback(item) {
            const rate = tingkatPenyusutan / 100;
            const subtotal = item.harga * item.qty;
            return subtotal * Math.pow(1 - rate, item.umur_produk_bulan || 0);
        }

        function renderItems() {
            const tbody = document.getElementById('items-tbody');
            tbody.innerHTML = '';
            document.getElementById('items-empty').style.display = items.length ? 'none' : '';

            let totalBeli = 0;
            let grandTotal = 0;
            items.forEach((item, idx) => {
                const subtotal = item.harga * item.qty;
                const nilai = nilaiBuyback(item);
                const penyusutan = subtotal - nilai;
                totalBeli += subtotal;
                grandTotal += nilai;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.nama_produk}</td>
                    <td><input type="number" min="0" value="${item.harga}" style="width:100px;" onchange="updateItem(${idx}, 'harga', this.value)"></td>
                    <td><input type="number" min="1" value="${item.qty}" style="width:65px;" onchange="updateItem(${idx}, 'qty', this.value)"></td>
                    <td class="tnum">${rp(subtotal)}</td>
                    <td><input type="date" value="${item.tanggal_ed}" style="width:145px;" onchange="updateItem(${idx}, 'tanggal_ed', this.value)"></td>
                    <td><input type="number" min="0" value="${item.umur_produk_bulan}" style="width:65px;" onchange="updateItem(${idx}, 'umur_produk_bulan', this.value)"></td>
                    <td class="tnum" style="color:var(--critical);">${rp(penyusutan)}</td>
                    <td class="tnum" style="font-weight:600;">${rp(nilai)}</td>
                    <td><button type="button" class="btn btn-danger" style="width:auto; font-size:11px; padding:4px 8px;" onclick="removeItem(${idx})">Hapus</button></td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('total-beli').textContent = rp(totalBeli);
            document.getElementById('total-penyusutan').textContent = rp(totalBeli - grandTotal);
            document.getElementById('grand-total').textContent = rp(grandTotal);

            const hidden = document.getElementById('hidden-items');
            hidden.innerHTML = items.map((item, idx) => `
                <input type="hidden" name="items[${idx}][produk_id]" value="${item.produk_id ?? ''}">
                <input type="hidden" name="items[${idx}][nama_produk]" value="${item.nama_produk}">
                <input type="hidden" name="items[${idx}][harga]" value="${item.harga}">
                <input type="hidden" name="items[${idx}][qty]" value="${item.qty}">
                <input type="hidden" name="items[${idx}][tanggal_ed]" value="${item.tanggal_ed}">
                <input type="hidden" name="items[${idx}][umur_produk_bulan]" value="${item.umur_produk_bulan}">
            `).join('');
        }

        document.getElementById('buyback-form').addEventListener('submit', function (e) {
            if (items.length === 0) {
                e.preventDefault();
                alert('Pilih minimal satu produk.');
            }
        });

        renderItems();
    </script>
@endsection
