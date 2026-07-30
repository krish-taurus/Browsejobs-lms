<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeJobStatusRequest extends FormRequest
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
            // Publishing has its own endpoint; only pause/close here.
            'status' => ['required', Rule::in(['paused', 'closed'])],
        ];
    }
}
