<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Labs\UpsertCodingLabRequest;
use App\Models\CodingLab;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;

/**
 * Admin coding-lab content (PRD §6.5). Gated by `can:manage-curriculum`; unlike the
 * student view, this returns the full lab including hidden test cases.
 */
final class CodingLabController extends Controller
{
    public function show(Lesson $lesson): JsonResponse
    {
        $lab = CodingLab::query()->where('lesson_id', $lesson->id)->first();

        return response()->json(['data' => $lab === null ? null : $this->payload($lab)]);
    }

    public function upsert(UpsertCodingLabRequest $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->type === LessonType::CodingLab, 422, 'Lesson is not a coding lab.');

        $lab = CodingLab::query()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'tenant_id' => $lesson->tenant_id,
                'language' => $request->string('language')->toString(),
                'instructions' => $request->input('instructions'),
                'starter_code' => $request->input('starter_code'),
                'test_cases' => $request->input('test_cases', []),
                'time_limit_ms' => $request->integer('time_limit_ms') ?: null,
            ],
        );

        return response()->json(['data' => $this->payload($lab->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CodingLab $lab): array
    {
        return [
            'lesson_id' => $lab->lesson_id,
            'language' => $lab->language->value,
            'instructions' => $lab->instructions,
            'starter_code' => $lab->starter_code,
            'test_cases' => $lab->test_cases ?? [],
            'time_limit_ms' => $lab->time_limit_ms,
        ];
    }
}
