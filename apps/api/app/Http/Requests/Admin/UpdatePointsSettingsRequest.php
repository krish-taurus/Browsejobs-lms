<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePointsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-monetization.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_points' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'punctuality_points' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'quiz_points' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'lab_points' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'streak_day_points' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'mock_points' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'daily_cap' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'attendance_min_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
