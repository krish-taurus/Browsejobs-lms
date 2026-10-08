<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for mock-interview blueprints (PRD §6.6), plus a feed of recent
 * completed mocks so staff can eyeball scorecard quality. Behind
 * can:manage-curriculum — blueprints are curriculum in interview form.
 */
final class MockBlueprintController extends Controller
{
    /**
     * Every student interview of one kind, newest first — the four admin
     * tables under /admin/ai-interviews/{practice|voice|job|cv}.
     */
    public function interviews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', MockInterview::KINDS)],
            'status' => ['nullable', 'string', 'in:in_progress,completed,abandoned'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $page = MockInterview::query()
            ->ofKind($validated['kind'])
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['q'] ?? null, fn ($q, $term) => $q->whereHas('student', fn ($s) => $s
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%')))
            ->with(['student:id,name,email,phone', 'blueprint:id,role_title'])
            ->orderByDesc('id')
            ->paginate(50);

        $counts = collect(MockInterview::KINDS)->mapWithKeys(fn (string $k) => [
            $k => MockInterview::query()->ofKind($k)->count(),
        ]);

        return response()->json([
            'data' => [
                'kind' => $validated['kind'],
                'counts' => $counts,
                'interviews' => collect($page->items())->map(fn (MockInterview $m) => [
                    'id' => $m->id,
                    'student' => $m->student?->name,
                    'email' => $m->student?->email,
                    'phone' => $m->student?->phone,
                    'role_title' => $m->blueprint?->role_title,
                    'status' => $m->status,
                    'overall_score' => $m->overall_score,
                    'scorecard_source' => $m->scorecard_source,
                    'duration_seconds' => $m->duration_seconds,
                    'started_at' => $m->started_at?->toIso8601String(),
                    'completed_at' => $m->completed_at?->toIso8601String(),
                ])->values(),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'blueprints' => MockBlueprint::query()
                    ->with('course:id,name')
                    ->orderByDesc('id')
                    ->get(),
                'recent_mocks' => MockInterview::query()
                    ->where('status', MockInterview::STATUS_COMPLETED)
                    ->with(['student:id,name', 'blueprint:id,role_title'])
                    ->orderByDesc('completed_at')
                    ->limit(20)
                    ->get()
                    ->map(fn (MockInterview $m) => [
                        'id' => $m->id,
                        'student' => $m->student?->name,
                        'role_title' => $m->blueprint?->role_title,
                        'overall_score' => $m->overall_score,
                        'scorecard_source' => $m->scorecard_source,
                        'completed_at' => $m->completed_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $course = Course::query()->findOrFail((int) $data['course_id']);

        $blueprint = MockBlueprint::query()->create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'role_title' => $data['role_title'],
            'skill' => $data['skill'] ?? null,
            'competencies' => $data['competencies'],
            'opening_question' => $data['opening_question'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['data' => $blueprint->load('course:id,name')], 201);
    }

    public function update(Request $request, MockBlueprint $blueprint): JsonResponse
    {
        $data = $this->validated($request, updating: true);
        unset($data['course_id']); // A blueprint stays with its course.

        $blueprint->update($data);

        return response()->json(['data' => $blueprint->load('course:id,name')]);
    }

    public function destroy(MockBlueprint $blueprint): JsonResponse
    {
        $blueprint->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'course_id' => [$updating ? 'sometimes' : 'required', 'integer'],
            'role_title' => [$required, 'string', 'max:190'],
            'skill' => ['nullable', 'string', 'max:100'],
            'competencies' => [$required, 'array', 'min:1', 'max:8'],
            'competencies.*' => ['string', 'max:100'],
            'opening_question' => [$required, 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
