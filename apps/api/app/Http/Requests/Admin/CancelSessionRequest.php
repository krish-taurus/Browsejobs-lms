<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancel a live class (PRD §6.3). The reason is shown to students in the cancellation
 * notice that goes out to the whole batch the moment this is saved.
 */
final class CancelSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware enforces can:teach-classes
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
