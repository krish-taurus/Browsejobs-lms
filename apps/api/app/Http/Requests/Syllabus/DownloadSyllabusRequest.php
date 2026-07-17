<?php

declare(strict_types=1);

namespace App\Http\Requests\Syllabus;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public, lead-gated syllabus download (PRD §6.1/§6.2 lead magnet). Captures a lead
 * (name + phone, DPDP consent) then returns a signed download URL. Mirrors the public
 * Leads\StoreLeadRequest gate.
 */
final class DownloadSyllabusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:190'],
            // DPDP: explicit consent is mandatory on every lead form.
            'consent' => ['accepted'],
        ];
    }
}
