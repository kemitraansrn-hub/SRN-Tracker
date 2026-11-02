{{-- Field form lead, dipakai di halaman tambah & edit. --}}
@php
    $select = fn (string $field) => old($field, $lead->{$field});
    $pipelineLocked ??= false;
@endphp

<div class="field-row">
    <div class="field" style="flex:1; min-width:200px;">
        <label>Tanggal masuk *</label>
        <input type="date" name="entry_date" required value="{{ old('entry_date', $lead->entry_date?->format('Y-m-d')) }}">
        @error('entry_date') <div class="field-error">{{ $message }}</div> @enderror
    </div>
    <div class="field" style="flex:1; min-width:200px;">
        <label>Channel sumber *</label>
        <select name="channel_id" class="select-pill" required>
            <option value="">Pilih channel...</option>
            @foreach ($channels as $channel)
                <option value="{{ $channel->id }}" {{ $select('channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name }}</option>
            @endforeach
        </select>
        @error('channel_id') <div class="field-error">{{ $message }}</div> @enderror
    </div>
</div>

<div class="field">
    <label>Nama lead @unless ($lead->is_legacy)*@endunless</label>
    <input type="text" name="name" @unless ($lead->is_legacy) required @endunless maxlength="150" placeholder="Contoh: Toko Skincare Glowing Bogor" value="{{ old('name', $lead->name) }}">
    @error('name') <div class="field-error">{{ $message }}</div> @enderror
</div>

<div class="field-row">
    <div class="field" style="flex:1; min-width:200px;">
        <label>Nomor WhatsApp *</label>
        <input type="tel" name="whatsapp" required inputmode="tel" placeholder="081234567890" value="{{ old('whatsapp', $lead->whatsapp) }}">
        @error('whatsapp')
            <div class="field-error">{{ $message }}</div>
        @else
            <div style="font-size:11px; color:var(--ink-faint); margin-top:4px;">Otomatis diubah ke format 62xxx.</div>
        @enderror
    </div>
    <div class="field" style="flex:1; min-width:200px;">
        <label>Kota</label>
        <input type="text" name="city" maxlength="100" placeholder="Opsional" value="{{ old('city', $lead->city) }}">
        @error('city') <div class="field-error">{{ $message }}</div> @enderror
    </div>
</div>

<fieldset style="border:1px solid var(--line); border-radius:10px; padding:14px 16px; margin-bottom:16px;"
    id="akuisisi-score-fieldset" data-priorities="{{ json_encode($priorities) }}" data-legacy="{{ $lead->is_legacy ? '1' : '0' }}">
    <legend style="padding:0 6px; font-size:13px; font-weight:600; color:var(--ink);">Penilaian prioritas</legend>

    <div class="field-row">
        @foreach ([
            ['field' => 'business_status_id', 'label' => 'Status bisnis', 'options' => $businessStatuses],
            ['field' => 'interest_level_id', 'label' => 'Respon / minat lead', 'options' => $interestLevels],
            ['field' => 'capital_status_id', 'label' => 'Status modal', 'options' => $capitalStatuses],
        ] as $component)
            <div class="field" style="flex:1; min-width:160px; margin-bottom:0;">
                <label>{{ $component['label'] }}</label>
                <select name="{{ $component['field'] }}" class="select-pill akuisisi-score-input">
                    <option value="">Belum diketahui</option>
                    @foreach ($component['options'] as $option)
                        <option value="{{ $option->id }}" data-score="{{ $option->score }}" {{ $select($component['field']) == $option->id ? 'selected' : '' }}>{{ $option->name }} ({{ $option->score }})</option>
                    @endforeach
                </select>
                @error($component['field']) <div class="field-error">{{ $message }}</div> @enderror
            </div>
        @endforeach
    </div>

    <div style="display:flex; align-items:center; gap:8px; background:var(--surface-alt); border-radius:8px; padding:10px 12px; margin-top:12px;">
        <span style="font-size:12px; color:var(--ink-muted);">Prioritas:</span>
        <span id="akuisisi-score-badge">@include('partials.akuisisi-priority-badge', ['priority' => $lead->priority])</span>
        <span id="akuisisi-score-detail" style="font-size:12px; font-weight:600; color:var(--ink);">{{ $lead->score !== null ? $lead->score.' poin' : '' }}</span>
    </div>
    <div style="font-size:11px; color:var(--ink-faint); margin-top:8px;">
        @if ($lead->is_legacy)
            Data lama: prioritas asli dipertahankan sampai salah satu penilaian di atas diubah.
        @else
            Total poin (kosong = 1): 8-9 Hot, 5-7 Warm, 3-4 Cold.
        @endif
    </div>
</fieldset>

<div class="field">
    <label>Tahapan pipeline @unless ($pipelineLocked)*@endunless</label>
    <select name="pipeline_stage_id" class="select-pill" style="max-width:320px;" @if ($pipelineLocked) disabled @else required @endif>
        <option value="">Pilih tahapan...</option>
        @foreach ($pipelineStages as $stage)
            <option value="{{ $stage->id }}" {{ $select('pipeline_stage_id') == $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
        @endforeach
    </select>
    @error('pipeline_stage_id')
        <div class="field-error">{{ $message }}</div>
    @else
        @if ($pipelineLocked)
            <div style="font-size:11px; color:var(--ink-faint); margin-top:4px;">
                Lead ini sudah punya riwayat follow-up; ubah tahapan lewat
                <a href="{{ route('akuisisi-leads.show', $lead) }}#catat-followup">Catat Follow-up</a>.
            </div>
        @endif
    @enderror
</div>

<script>
    (function () {
        var fieldset = document.getElementById('akuisisi-score-fieldset');
        if (!fieldset) return;
        var priorities = JSON.parse(fieldset.dataset.priorities || '[]');
        var isLegacy = fieldset.dataset.legacy === '1';
        var touched = false;

        function scoreOf(select) {
            if (!select.value) return 1;
            var opt = select.options[select.selectedIndex];
            return parseInt(opt.dataset.score || '1', 10);
        }

        function priorityFor(score) {
            for (var i = 0; i < priorities.length; i++) {
                if (score >= priorities[i].min_score && score <= priorities[i].max_score) return priorities[i];
            }
            return null;
        }

        function badgeHtml(priority) {
            var cls = 'chip-neutral';
            if (priority) {
                cls = priority.code === 'HOT' ? 'chip-critical' : (priority.code === 'WARM' ? 'chip-warn' : 'chip-accent');
            }
            return '<span class="chip ' + cls + '">' + (priority ? priority.name : 'Belum dinilai') + '</span>';
        }

        function recalc() {
            if (isLegacy && !touched) return;
            var inputs = fieldset.querySelectorAll('.akuisisi-score-input');
            var score = 0;
            inputs.forEach(function (s) { score += scoreOf(s); });
            var priority = priorityFor(score);
            document.getElementById('akuisisi-score-badge').innerHTML = badgeHtml(priority);
            document.getElementById('akuisisi-score-detail').textContent = score + ' poin';
        }

        fieldset.querySelectorAll('.akuisisi-score-input').forEach(function (select) {
            select.addEventListener('change', function () { touched = true; recalc(); });
        });
    })();
</script>
