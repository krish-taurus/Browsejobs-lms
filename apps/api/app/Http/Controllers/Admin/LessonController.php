<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CurriculumNodeRequest;
use App\Models\Lesson;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;

final class LessonController extends Controller
{
    public function store(CurriculumNodeRequest $request): JsonResponse
    {
        $topic = Topic::query()->findOrFail((int) $request->input('topic_id'));

        $lesson = Lesson::query()->create([
            'tenant_id' => $topic->tenant_id,
            'topic_id' => $topic->id,
            'title' => $request->string('title')->toString(),
            'type' => $request->input('type', LessonType::LiveClass->value),
            'position' => (int) $request->input('position', $topic->lessons()->count()),
        ]);

        return response()->json(['data' => $lesson], 201);
    }

    public function update(CurriculumNodeRequest $request, Lesson $lesson): JsonResponse
    {
        $lesson->update($request->safe()->only(['title', 'type', 'position']));

        return response()->json(['data' => $lesson]);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $lesson->delete();

        return response()->json(status: 204);
    }
}
