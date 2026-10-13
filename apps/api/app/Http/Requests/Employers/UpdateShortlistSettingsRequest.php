<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use App\Models\JobShortlistEntry;
use App\Support\Employers\ShortlistSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The shortlist agent's settings for one JD. */
final class UpdateShortlistSettingsRequest extends FormRequest
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
            'top_n' => ['required', 'integer', 'min:1', 'max:'.ShortlistSettings::MAX_TOP_N],
            'min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'sources' => ['present', 'array'],
            'sources.*' => ['string', Rule::in(JobShortlistEntry::SOURCES)],
            'auto_on_publish' => ['required', 'boolean'],
            'students_need_readiness' => ['sometimes', 'boolean'],
            'require_interview' => ['sometimes', 'boolean'],
            'first_cv_bar' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'first_interview_bar' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'second_cv_bar' => ['sometimes', 'integer', 'min:0', 'max:100', Rule::when($this->has('first_cv_bar'), 'lte:first_cv_bar')],
            'second_interview_bar' => ['sometimes', 'integer', 'min:0', 'max:100', Rule::when($this->has('first_interview_bar'), 'lte:first_interview_bar')],
            'auto_invite' => ['sometimes', 'boolean'],
            'confirm_rounds' => ['sometimes', 'boolean'],
            'interview_time' => ['sometimes', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'round_pass_mark' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }
}
