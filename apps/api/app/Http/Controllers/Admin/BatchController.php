<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Batches\CreateBatch;
use App\Enums\BatchType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBatchRequest;
use App\Models\Batch;
use App\Models\BatchModuleTrainer;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Trainer allocation for a batch: the lead trainer, each course module with its
     * assigned trainer (if any), and the trainers available to pick from.
     */
    public function moduleTrainers(Batch $batch): JsonResponse
    {
        $modules = Module::query()
            ->where('course_id', $batch->course_id)
            ->orderBy('position')->orderBy('id')
            ->get(['id', 'name']);

        $assigned = $batch->moduleTrainers()->pluck('user_id', 'module_id');

        return response()->json(['data' => [
            'lead_trainer_id' => $batch->trainer_id,
            'modules' => $modules->map(fn (Module $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'trainer_id' => $assigned[$m->id] ?? null,
            ])->all(),
            'trainers' => $this->trainerOptions(),
        ]]);
    }

    /**
     * Set the lead trainer and per-module trainer assignments for a batch. A module
     * with a null trainer clears its override (it falls back to the lead trainer).
     */
    public function setModuleTrainers(Request $request, Batch $batch): JsonResponse
    {
        $validTrainerIds = $this->trainerIds();
        $data = $request->validate([
            'lead_trainer_id' => ['nullable', 'integer'],
            'assignments' => ['array'],
            'assignments.*.module_id' => ['required', 'integer'],
            'assignments.*.trainer_id' => ['nullable', 'integer'],
        ]);

        $moduleIds = Module::query()->where('course_id', $batch->course_id)->pluck('id')->all();

        DB::transaction(function () use ($batch, $data, $validTrainerIds, $moduleIds): void {
            $lead = $data['lead_trainer_id'] ?? null;
            $batch->update(['trainer_id' => in_array($lead, $validTrainerIds, true) ? $lead : null]);

            foreach ($data['assignments'] ?? [] as $a) {
                $moduleId = (int) $a['module_id'];
                if (! in_array($moduleId, $moduleIds, true)) {
                    continue; // module must belong to this batch's course
                }
                $trainerId = $a['trainer_id'] ?? null;

                if ($trainerId === null || ! in_array((int) $trainerId, $validTrainerIds, true)) {
                    BatchModuleTrainer::query()->where('batch_id', $batch->id)->where('module_id', $moduleId)->delete();

                    continue;
                }

                BatchModuleTrainer::query()->updateOrCreate(
                    ['batch_id' => $batch->id, 'module_id' => $moduleId],
                    ['tenant_id' => $batch->tenant_id, 'user_id' => (int) $trainerId],
                );
            }
        });

        return $this->moduleTrainers($batch->refresh());
    }

    /** @return list<array{id:int, name:string}> */
    private function trainerOptions(): array
    {
        return User::query()
            ->where('user_type', 'staff')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['trainer', 'admin']))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /** @return list<int> */
    private function trainerIds(): array
    {
        return User::query()
            ->where('user_type', 'staff')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['trainer', 'admin']))
            ->pluck('id')->all();
    }
}
