<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Batches\CreateBatch;
use App\Enums\BatchType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBatchRequest;
use App\Models\Batch;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

final class BatchController extends Controller
{
    public function index(): JsonResponse
    {
        $batches = Batch::query()
            ->with('course:id,code,name')
            ->withCount('members')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $batches]);
    }

    public function store(StoreBatchRequest $request, CreateBatch $createBatch): JsonResponse
    {
        $course = Course::query()->findOrFail((int) $request->input('course_id'));

        $batch = $createBatch->handle(
            $course,
            BatchType::from($request->string('type')->toString()),
            $request->safe()->only(['capacity', 'starts_on', 'ends_on']),
            $request->input('number'),
        );

        return response()->json(['data' => $batch->load('course:id,code,name')], 201);
    }

    public function show(Batch $batch): JsonResponse
    {
        $batch->load([
            'course:id,code,name',
            'members' => fn ($q) => $q->orderByDesc('id'),
            'members.student:id,name,email,phone',
        ]);

        return response()->json(['data' => $batch]);
    }
}
