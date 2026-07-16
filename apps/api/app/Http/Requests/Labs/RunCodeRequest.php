<?php

declare(strict_types=1);

namespace App\Http\Requests\Labs;

use Illuminate\Foundation\Http\FormRequest;

final class RunCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:100000'],
        ];
    }
}
