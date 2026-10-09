<?php

declare(strict_types=1);

namespace App\Http\Requests\Taurus;

use App\Models\TaurusAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Which floor the console is looking at (defaults to ops). */
final class FloorStateRequest extends FormRequest
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
            'floor' => ['sometimes', 'string', Rule::in(TaurusAgent::FLOORS)],
        ];
    }

    public function floor(): string
    {
        return (string) $this->validated('floor', 'ops');
    }
}
