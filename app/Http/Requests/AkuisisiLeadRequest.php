<?php

namespace App\Http\Requests;

use App\Models\AkuisisiLead;
use App\Support\AkuisisiPhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Diporting dari App\Http\Requests\LeadRequest di PartnerAcquire CRM. */
class AkuisisiLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')) ?: null,
            'whatsapp' => AkuisisiPhoneNumber::normalize($this->input('whatsapp')),
        ]);
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            // Data lama boleh tanpa nama; lead baru wajib bernama.
            'name' => [Rule::requiredIf(! $this->route('lead')?->is_legacy), 'nullable', 'string', 'max:150'],
            'whatsapp' => ['required', 'regex:/^62\d{8,13}$/', $this->uniqueWhatsapp(...)],
            'city' => ['nullable', 'string', 'max:100'],
            'channel_id' => ['required', Rule::exists('akuisisi_channels', 'id')->where('is_active', true)],
            'business_status_id' => ['nullable', Rule::exists('akuisisi_business_statuses', 'id')],
            'interest_level_id' => ['nullable', Rule::exists('akuisisi_interest_levels', 'id')],
            'capital_status_id' => ['nullable', Rule::exists('akuisisi_capital_statuses', 'id')],
            // Setelah ada follow-up, tahapan diubah lewat form Catat Follow-up (lihat leadData()).
            'pipeline_stage_id' => [Rule::requiredIf(! $this->pipelineLocked()), 'nullable', Rule::exists('akuisisi_pipeline_stages', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'entry_date' => 'Tanggal masuk',
            'name' => 'Nama lead',
            'whatsapp' => 'Nomor WhatsApp',
            'city' => 'Kota',
            'channel_id' => 'Channel sumber',
            'business_status_id' => 'Status bisnis',
            'interest_level_id' => 'Respon / minat lead',
            'capital_status_id' => 'Status modal',
            'pipeline_stage_id' => 'Tahapan pipeline',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'max' => ':attribute maksimal :max karakter.',
            'exists' => ':attribute yang dipilih tidak valid.',
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
        ];
    }

    public function pipelineLocked(): bool
    {
        return (bool) $this->route('lead')?->followups()->exists();
    }

    /** Data lead siap simpan. Tahapan pipeline diabaikan bila dikunci oleh riwayat follow-up. */
    public function leadData(): array
    {
        $data = $this->validated();

        if ($this->pipelineLocked()) {
            unset($data['pipeline_stage_id']);
        }

        return $data;
    }

    /**
     * Nomor WA harus unik, termasuk terhadap lead yang sudah dihapus.
     * Jika milik lead terhapus, tawarkan pemulihan lewat flash 'restorable_lead'.
     */
    private function uniqueWhatsapp(string $attribute, mixed $value, Closure $fail): void
    {
        $existing = AkuisisiLead::withTrashed()
            ->where('whatsapp', $value)
            ->when($this->route('lead'), fn ($query, AkuisisiLead $lead) => $query->whereKeyNot($lead->getKey()))
            ->first();

        if (! $existing) {
            return;
        }

        if ($existing->trashed()) {
            session()->flash('restorable_lead', ['id' => $existing->id, 'name' => $existing->display_name]);
            $fail("Nomor WhatsApp ini milik lead yang sudah dihapus ({$existing->display_name}).");

            return;
        }

        $fail("Nomor WhatsApp sudah terdaftar atas nama {$existing->display_name}.");
    }
}
