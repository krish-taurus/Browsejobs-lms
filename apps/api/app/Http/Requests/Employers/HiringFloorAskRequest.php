<?php

declare(strict_types=1);

namespace App\Http\Requests\Employers;

use Illuminate\Foundation\Http\FormRequest;

/** A spoken question about the hiring floor; membership is checked in the controller. */
final class HiringFloorAskRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:500'],
            'job_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
