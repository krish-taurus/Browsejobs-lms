<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Curriculum\CloneModuleIntoCourse;
use App\Events\CurriculumChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CurriculumNodeRequest;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModuleController extends Controller
{
    public function store(CurriculumNodeRequest $request): JsonResponse
    {
        // Scoped lookup: a course id from another tenant 404s here.
        $course = Course::query()->findOrFail((int) $request->input('course_id'));

        $module = Module::query()->create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'name' => $request->string('name')->toString(),
            'position' => (int) $request->input('position', $course->modules()->count()),
        ]);

        CurriculumChanged::dispatch($course->id, (int) $course->tenant_id);

        return response()->json(['data' => $module], 201);
    }

    public function update(CurriculumNodeRequest $request, Module $module): JsonResponse
    {
        $module->update($request->safe()->only(['name', 'position']));

        CurriculumChanged::dispatch($module->course_id, (int) $module->tenant_id);

        return response()->json(['data' => $module]);
    }

    public function destroy(Module $module): JsonResponse
    {
        $courseId = $module->course_id;
        $tenantId = (int) $module->tenant_id;
        $module->delete();

        CurriculumChanged::dispatch($courseId, $tenantId);

        return response()->json(status: 204);
    }

    /**
     * Search existing modules across the tenant's courses so an admin can reuse
     * one instead of rebuilding it. Returns each module with its course and
     * program for context, plus topic/lesson counts.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $modules = Module::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->with(['course:id,name,program_id', 'course.program:id,name'])
            ->withCount(['topics'])
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'course_id', 'name']);

        return response()->json(['data' => $modules]);
    }

    /**
     * Clone a module (with its topics and lessons) into another course.
     * Route-model binding is tenant-scoped, so cross-tenant ids 404.
     */
    public function clone(Request $request, Module $module, CloneModuleIntoCourse $clone): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer'],
        ]);

        $target = Course::query()->findOrFail((int) $validated['course_id']);
        $copy = $clone->handle($module, $target);

        return response()->json(['data' => $copy], 201);
    }
}
