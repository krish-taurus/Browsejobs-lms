<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use App\Models\EmployerCandidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A candidate sheet for the shortlist agent: the client's own database or a
 * Naukri export, as .xlsx or .csv (≤ 10 MB).
 */
final class ImportEmployerCandidatesRequest extends FormRequest
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
            'source' => ['required', Rule::in(EmployerCandidate::SOURCES)],
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload an Excel (.xlsx) or CSV file. An old .xls file must be saved as .xlsx first.',
            'file.max' => 'The file is too large (10 MB maximum).',
        ];
    }
}
