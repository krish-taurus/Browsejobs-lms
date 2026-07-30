<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use App\Enums\EmployerApplicationStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MoveApplicationStageRequest extends FormRequest
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
            'stage' => ['required', Rule::enum(EmployerApplicationStage::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
