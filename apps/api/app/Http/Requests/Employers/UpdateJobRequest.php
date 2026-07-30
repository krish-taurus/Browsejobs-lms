<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controller enforces managesPipeline() via ResolvesMembership.
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'required', 'string', 'max:20000'],
            'role_family' => ['nullable', 'string', 'max:120'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:60'],
            'experience_min_years' => ['nullable', 'integer', 'min:0', 'max:40'],
            'experience_max_years' => ['nullable', 'integer', 'min:0', 'max:40'],
            'locations' => ['nullable', 'array', 'max:10'],
            'locations.*' => ['string', 'max:120'],
            'remote' => ['nullable', 'boolean'],
            'ctc_min_paise' => ['nullable', 'integer', 'min:0'],
            'ctc_max_paise' => ['nullable', 'integer', 'min:0'],
            'ctc_visible' => ['nullable', 'boolean'],
            'openings' => ['nullable', 'integer', 'min:1', 'max:500'],
            'knockout_questions' => ['nullable', 'array', 'max:10'],
            'knockout_questions.*.question' => ['required_with:knockout_questions', 'string', 'max:300'],
            'knockout_questions.*.disqualify_if' => ['nullable', 'string', 'max:120'],
        ];
    }
}
