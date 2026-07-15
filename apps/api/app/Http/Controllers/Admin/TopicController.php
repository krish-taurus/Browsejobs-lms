<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CurriculumNodeRequest;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;

final class TopicController extends Controller
{
    public function store(CurriculumNodeRequest $request): JsonResponse
    {
        $module = Module::query()->findOrFail((int) $request->input('module_id'));

        $topic = Topic::query()->create([
            'tenant_id' => $module->tenant_id,
            'module_id' => $module->id,
            'name' => $request->string('name')->toString(),
            'position' => (int) $request->input('position', $module->topics()->count()),
        ]);

        return response()->json(['data' => $topic], 201);
    }

    public function update(CurriculumNodeRequest $request, Topic $topic): JsonResponse
    {
        $topic->update($request->safe()->only(['name', 'position']));

        return response()->json(['data' => $topic]);
    }

    public function destroy(Topic $topic): JsonResponse
    {
        $topic->delete();

        return response()->json(status: 204);
    }
}
