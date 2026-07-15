<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CurriculumNodeRequest;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\JsonResponse;

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

        return response()->json(['data' => $module], 201);
    }

    public function update(CurriculumNodeRequest $request, Module $module): JsonResponse
    {
        $module->update($request->safe()->only(['name', 'position']));

        return response()->json(['data' => $module]);
    }

    public function destroy(Module $module): JsonResponse
    {
        $module->delete();

        return response()->json(status: 204);
    }
}
