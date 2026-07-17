<?php

declare(strict_types=1);

namespace App\Http\Requests\Syllabus;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual authoring / editing of a syllabus's prose by a curriculum manager. Route
 * middleware enforces can:manage-curriculum. Editing reverts the row to draft; the
 * last approved artifact keeps serving until re-approval.
 */
final class UpdateSyllabusRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:'.(int) config('syllabus.ai.max_chars', 12000)],
        ];
    }
}
