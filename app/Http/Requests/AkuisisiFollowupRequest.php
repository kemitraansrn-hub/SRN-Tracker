<?php

namespace App\Http\Requests;

use App\Models\AkuisisiFollowupStatus;
use App\Models\AkuisisiPriority;
use App\Services\AkuisisiNextFollowupScheduler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Diporting dari App\Http\Requests\FollowupRequest di PartnerAcquire CRM. */
class AkuisisiFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => trim((string) $this->input('note')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'followup_date' => ['required', 'date', 'before_or_equal:today'],
            'followup_status_id' => ['required', Rule::exists('akuisisi_followup_statuses', 'id')],
            'pipeline_stage_id' => ['required', Rule::exists('akuisisi_pipeline_stages', 'id')],
            'note' => ['nullable', 'string', 'max:2000'],
            'auto_schedule' => ['boolean'],
            'set_interest_proposal' => ['boolean'],
            'next_followup_date' => ['nullable', 'date', 'after_or_equal:followup_date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'followup_date' => 'Tanggal follow-up',
            'followup_status_id' => 'Hasil follow-up',
            'pipeline_stage_id' => 'Tahapan pipeline',
            'note' => 'Catatan',
            'next_followup_date' => 'Jadwal next follow-up',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'max' => ':attribute maksimal :max karakter.',
            'exists' => ':attribute yang dipilih tidak valid.',
            'followup_date.before_or_equal' => 'Tanggal follow-up tidak boleh melewati hari ini.',
            'next_followup_date.after_or_equal' => 'Jadwal next follow-up tidak boleh sebelum tanggal follow-up.',
        ];
    }

    /** Data follow-up siap simpan, dengan jadwal next yang dihitung server. */
    public function followupData(?AkuisisiPriority $priority): array
    {
        $data = $this->safe()->only(['followup_date', 'followup_status_id', 'pipeline_stage_id', 'note']);
        $manualDate = $this->validated('next_followup_date');

        return [
            ...$data,
            ...app(AkuisisiNextFollowupScheduler::class)->resolve(
                $priority,
                AkuisisiFollowupStatus::findOrFail($data['followup_status_id']),
                Carbon::parse($data['followup_date']),
                $this->boolean('auto_schedule'),
                $manualDate ? Carbon::parse($manualDate) : null,
            ),
        ];
    }
}
