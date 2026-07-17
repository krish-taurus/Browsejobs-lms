<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Move a live class to a new time (PRD §6.3). The reason is required because it is
 * shown to students in the reschedule notification — "moved to Saturday 4pm" is only
 * useful with the why.
 */
final class RescheduleSessionRequest extends FormRequest
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
            'scheduled_start' => ['required', 'date', 'after:now'],
            'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
