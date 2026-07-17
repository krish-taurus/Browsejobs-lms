<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Schedule a live class for a batch (PRD §6.3). An "extra session" is just another
 * session on the same batch — its recording lands in that batch's library like any
 * other, which is why no separate temp-batch entity is needed.
 */
final class ScheduleSessionRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'scheduled_start' => ['required', 'date', 'after:now'],
            'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
            'topic_id' => ['nullable', 'integer'],
        ];
    }
}
