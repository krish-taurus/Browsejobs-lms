<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add an existing student to an additional batch (PRD §6.13 ops). A student may sit in
 * several batches at once, so this adds a membership without disturbing the others —
 * the move-between-batches case is the separate transfer action.
 */
final class EnrollStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware enforces can:manage-rosters
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer'],
        ];
    }
}
