{{--
    Field form follow-up, dipakai di halaman detail lead (catat baru) dan halaman edit follow-up.
    Butuh: $lead, $followup, $statuses, $pipelineStages, $schedulePriority (acuan jadwal otomatis, boleh null).
--}}
@php
    $autoChecked = (bool) old('auto_schedule', $schedulePriority && ! $followup->is_next_manual);
    $currentStage = $lead->pipelineStage;
    $statusGroups = ['Masih berjalan' => $statuses->where('is_archived', false), 'Hasil akhir' => $statuses->where('is_archived', true)];
    // Saran "Minta Proposal" hanya untuk catat baru, bila penilaian minat lead masih di bawahnya.
    $suggestProposal = ! $followup->exists && ($lead->interestLevel?->score ?? 1) < 3;
@endphp

<div id="akuisisi-followup-fields"
    data-interval="{{ $schedulePriority?->interval_days }}"
    data-current-stage-order="{{ $followup->exists ? 0 : ($currentStage?->sort_order ?? 0) }}"
    data-current-stage-name="{{ $currentStage?->name }}"
    data-profile-incomplete="{{ (! $lead->business_status_id || ! $lead->capital_status_id) ? '1' : '0' }}"
    data-rules="{{ json_encode(\App\Support\AkuisisiPipelineRules::unusualCombinations()) }}">

    <div class="field-row">
        <div class="field" style="flex:1; min-width:180px;">
            <label>Tanggal follow-up *</label>
            <input type="date" id="akuisisi-followup-date" name="followup_date" required max="{{ today()->format('Y-m-d') }}"
                value="{{ old('followup_date', $followup->followup_date?->format('Y-m-d')) }}">
            @error('followup_date') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="field" style="flex:1; min-width:200px;">
            <label>Hasil follow-up *</label>
            <select id="akuisisi-followup-status" name="followup_status_id" class="select-pill" required>
                <option value="">Pilih hasil...</option>
                @foreach ($statusGroups as $group => $options)
                    <optgroup label="{{ $group }}">
                        @foreach ($options as $status)
                            <option value="{{ $status->id }}" data-code="{{ $status->code }}"
                                data-stops-schedule="{{ $status->stops_schedule ? '1' : '0' }}"
                                data-auto-schedule="{{ $status->auto_schedule ? '1' : '0' }}"
                                {{ old('followup_status_id', $followup->followup_status_id) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('followup_status_id') <div class="field-error">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="field">
        <label>Tahapan pipeline *</label>
        <select id="akuisisi-pipeline-stage" name="pipeline_stage_id" class="select-pill" style="max-width:320px;" required>
            <option value="">Pilih tahapan...</option>
            @foreach ($pipelineStages as $stage)
                <option value="{{ $stage->id }}" data-code="{{ $stage->code }}" data-order="{{ $stage->sort_order }}"
                    data-goal="{{ $stage->goal }}" data-exit="{{ $stage->exit_criteria }}"
                    {{ old('pipeline_stage_id', $followup->pipeline_stage_id) == $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
            @endforeach
        </select>
        @error('pipeline_stage_id') <div class="field-error">{{ $message }}</div> @enderror

        <div id="akuisisi-stage-hint" style="display:none; background:var(--surface-alt); border-radius:8px; padding:8px 12px; margin-top:8px; font-size:11.5px; color:var(--ink-muted);">
            <div><strong style="color:var(--ink);">Tujuan:</strong> <span id="akuisisi-stage-goal"></span></div>
            <div style="margin-top:3px;"><strong style="color:var(--ink);">Naik tahap jika:</strong> <span id="akuisisi-stage-exit"></span></div>
        </div>

        @if ($suggestProposal)
            <label id="akuisisi-interest-suggest" style="display:none; align-items:flex-start; gap:8px; border:1px solid var(--accent); background:var(--accent-soft); border-radius:8px; padding:8px 12px; margin-top:8px; font-size:11.5px; color:var(--accent-ink);">
                <input type="checkbox" name="set_interest_proposal" value="1" style="margin-top:2px;" {{ old('set_interest_proposal') ? 'checked' : '' }}>
                <span>Sekaligus ubah Respon / Minat menjadi <strong>Minta Proposal</strong> (sekarang: {{ $lead->interestLevel?->name ?? 'belum diketahui' }}). Skor prioritas ikut dihitung ulang.</span>
            </label>
        @endif
    </div>

    <div class="field">
        <label>Catatan</label>
        <textarea name="note" rows="3" maxlength="2000" placeholder="Ringkasan percakapan, kebutuhan, keberatan...">{{ old('note', $followup->note) }}</textarea>
        @error('note') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    <div class="field">
        <label>Jadwal next follow-up</label>
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px;">
            <input type="date" id="akuisisi-next-date" name="next_followup_date" style="width:auto;"
                value="{{ old('next_followup_date', $followup->next_followup_date?->format('Y-m-d')) }}">

            <label style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink);">
                <input type="hidden" name="auto_schedule" value="0">
                <input type="checkbox" id="akuisisi-auto-schedule" name="auto_schedule" value="1"
                    {{ $autoChecked ? 'checked' : '' }} {{ ! $schedulePriority ? 'disabled' : '' }}>
                Hitung otomatis
                @if ($schedulePriority)
                    <span style="color:var(--ink-muted);">({{ $schedulePriority->name }}: H+{{ $schedulePriority->interval_days }})</span>
                @endif
            </label>
        </div>
        @error('next_followup_date')
            <div class="field-error">{{ $message }}</div>
        @else
            <div id="akuisisi-next-hint" style="font-size:11px; color:var(--ink-faint); margin-top:4px;">
                @if (! $schedulePriority)
                    Lead belum punya prioritas, jadwal diisi manual. Kosongkan jika tidak perlu dijadwalkan.
                @else
                    Lepas centang untuk mengisi tanggal sendiri. Hasil akhir tidak dijadwalkan otomatis.
                @endif
            </div>
        @enderror
    </div>
</div>

<script>
    (function () {
        var root = document.getElementById('akuisisi-followup-fields');
        if (!root || root.dataset.wired === '1') return;
        root.dataset.wired = '1';

        var interval = root.dataset.interval ? parseInt(root.dataset.interval, 10) : null;
        var currentStageOrder = parseInt(root.dataset.currentStageOrder || '0', 10);
        var currentStageName = root.dataset.currentStageName || '';
        var profileIncomplete = root.dataset.profileIncomplete === '1';
        var rules = JSON.parse(root.dataset.rules || '[]');

        var dateInput = document.getElementById('akuisisi-followup-date');
        var statusSelect = document.getElementById('akuisisi-followup-status');
        var stageSelect = document.getElementById('akuisisi-pipeline-stage');
        var nextDateInput = document.getElementById('akuisisi-next-date');
        var autoCheckbox = document.getElementById('akuisisi-auto-schedule');
        var stageHint = document.getElementById('akuisisi-stage-hint');
        var stageGoal = document.getElementById('akuisisi-stage-goal');
        var stageExit = document.getElementById('akuisisi-stage-exit');
        var interestSuggest = document.getElementById('akuisisi-interest-suggest');

        function selectedOpt(select) {
            return select.options[select.selectedIndex] || null;
        }

        function recalcNextDate() {
            var statusOpt = selectedOpt(statusSelect);
            if (!statusOpt || !statusOpt.value) return;

            var stops = statusOpt.dataset.stopsSchedule === '1';
            var auto = statusOpt.dataset.autoSchedule === '1';

            if (stops) {
                nextDateInput.value = '';
                nextDateInput.disabled = true;
                autoCheckbox.checked = false;
                autoCheckbox.disabled = true;
                return;
            }

            autoCheckbox.disabled = !interval;
            nextDateInput.disabled = autoCheckbox.checked && !!interval;

            if (autoCheckbox.checked && interval && dateInput.value) {
                var d = new Date(dateInput.value + 'T00:00:00');
                d.setDate(d.getDate() + interval);
                nextDateInput.value = d.toISOString().slice(0, 10);
            } else if (!auto && autoCheckbox.checked) {
                // status "hasil akhir" (bukan stops) tapi gak auto_schedule -> kosongkan, CS isi manual
                autoCheckbox.checked = false;
                nextDateInput.disabled = false;
            }
        }

        function updateStageHint() {
            var opt = selectedOpt(stageSelect);
            if (opt && opt.value && (opt.dataset.goal || opt.dataset.exit)) {
                stageGoal.textContent = opt.dataset.goal || '-';
                stageExit.textContent = opt.dataset.exit || '-';
                stageHint.style.display = '';
            } else {
                stageHint.style.display = 'none';
            }
        }

        function updateInterestSuggest() {
            if (!interestSuggest) return;
            var opt = selectedOpt(stageSelect);
            interestSuggest.style.display = (opt && opt.dataset.code === 'PITCHING') ? 'flex' : 'none';
        }

        if (dateInput) dateInput.addEventListener('change', recalcNextDate);
        if (statusSelect) statusSelect.addEventListener('change', function () { recalcNextDate(); });
        if (stageSelect) stageSelect.addEventListener('change', function () { updateStageHint(); updateInterestSuggest(); });
        if (autoCheckbox) autoCheckbox.addEventListener('change', recalcNextDate);

        updateStageHint();
        updateInterestSuggest();

        // Konfirmasi lunak buat kombinasi hasil x tahapan yang gak lazim (cuma peringatan, bukan larangan).
        var form = root.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                var statusOpt = selectedOpt(statusSelect);
                var stageOpt = selectedOpt(stageSelect);
                if (!statusOpt || !stageOpt) return;

                for (var i = 0; i < rules.length; i++) {
                    var rule = rules[i];
                    if (statusOpt.dataset.code === rule.status && rule.stages.indexOf(stageOpt.dataset.code) !== -1) {
                        var msg = rule.message.replace('{stage}', stageOpt.textContent.trim());
                        if (!confirm(msg + '\n\nLanjutkan simpan?')) { e.preventDefault(); return; }
                    }
                }

                var newOrder = parseInt(stageOpt.dataset.order || '0', 10);
                if (currentStageOrder && newOrder && newOrder < currentStageOrder) {
                    if (!confirm('Tahapan dipilih (' + stageOpt.textContent.trim() + ') mundur dari tahapan sekarang (' + currentStageName + '). Lanjutkan?')) { e.preventDefault(); return; }
                }

                if (profileIncomplete && newOrder > 1) {
                    if (!confirm('Status bisnis/modal lead ini belum lengkap, tapi tahapan dipilih sudah lanjut. Lanjutkan?')) { e.preventDefault(); return; }
                }
            });
        }
    })();
</script>
