<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Schedule a batch's whole class calendar in one action (PRD §6.3). The weekday
 * pattern + time + count generates the live-class series; each class still gets
 * its own Zoom meeting and reminder ladder via the scheduling engine.
 */
final class ScheduleSeriesRequest extends FormRequest
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
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'time' => ['required', 'date_format:H:i'],
            // Optional per-weekday time overrides, keyed by ISO weekday (1–7).
            'times' => ['nullable', 'array'],
            'times.*' => ['date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'count' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'title_prefix' => ['nullable', 'string', 'max:120'],
            'map_topics' => ['nullable', 'boolean'],
            'record' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'weekdays.required' => 'Pick at least one day of the week for classes.',
            'weekdays.*.between' => 'Days must be 1 (Monday) through 7 (Sunday).',
            'time.date_format' => 'Time must be in 24-hour HH:MM format.',
        ];
    }
}
