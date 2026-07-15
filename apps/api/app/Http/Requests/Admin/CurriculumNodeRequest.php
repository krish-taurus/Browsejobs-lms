<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\LessonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for curriculum nodes (module/topic/lesson). Parent ids are
 * validated for existence within the tenant by the controller's scoped lookup.
 */
final class CurriculumNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-curriculum.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'title' => ['sometimes', 'required', 'string', 'max:190'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'course_id' => ['sometimes', 'integer'],
            'module_id' => ['sometimes', 'integer'],
            'topic_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', Rule::enum(LessonType::class)],
        ];
    }
}
