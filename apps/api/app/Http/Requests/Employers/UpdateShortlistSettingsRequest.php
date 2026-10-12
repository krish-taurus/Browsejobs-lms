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
        ];
    }
}
