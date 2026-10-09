<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use Illuminate\Foundation\Http\FormRequest;

/** Optional single-role filter for the hiring floor; membership is checked in the controller. */
final class HiringFloorRequest extends FormRequest
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
            'job_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
