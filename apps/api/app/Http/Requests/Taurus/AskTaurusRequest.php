<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Models\TaurusAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A spoken question about one Taurus floor. */
final class AskTaurusRequest extends FormRequest
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
            'floor' => ['sometimes', 'string', Rule::in(TaurusAgent::FLOORS)],
        ];
    }
}
