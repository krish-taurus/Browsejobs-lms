<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Batches\CreateBatch;
use App\Actions\Batches\NotifyBatchAllocation;
use App\Enums\BatchType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBatchRequest;
use App\Models\Batch;
use App\Models\BatchMentor;
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
     * Staff allocation for a batch: the lead trainer, each course module with its
     * assigned trainer, the batch's mentors, and the people available to pick from.
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
            'mentor_ids' => $batch->batchMentors()->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
            'trainers' => $this->staffOptions(['trainer', 'admin']),
            'mentors' => $this->staffOptions(['mentor']),
        ]]);
    }

    /**
     * Set the lead trainer, per-module trainer assignments, and mentors for a batch.
     * A module with a null trainer falls back to the lead. Newly-allocated trainers
     * and mentors are notified automatically.
     */
    public function setModuleTrainers(Request $request, Batch $batch, NotifyBatchAllocation $notify): JsonResponse
    {
        $validTrainerIds = $this->staffIds(['trainer', 'admin']);
        $validMentorIds = $this->staffIds(['mentor']);
        $data = $request->validate([
            'lead_trainer_id' => ['nullable', 'integer'],
            'assignments' => ['array'],
            'assignments.*.module_id' => ['required', 'integer'],
            'assignments.*.trainer_id' => ['nullable', 'integer'],
            'mentor_ids' => ['array'],
            'mentor_ids.*' => ['integer'],
        ]);

        $moduleIds = Module::query()->where('course_id', $batch->course_id)->pluck('id')->all();

        // Capture the previous allocation so we only notify people who are new.
        $prevLead = $batch->trainer_id;
        $prevModuleTrainers = $batch->moduleTrainers()->pluck('user_id', 'module_id')->all();
        $prevMentors = $batch->batchMentors()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($batch, $data, $validTrainerIds, $validMentorIds, $moduleIds): void {
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

            // Replace the mentor set with the (valid) submitted ids.
            $mentorIds = array_values(array_intersect(array_map('intval', $data['mentor_ids'] ?? []), $validMentorIds));
            BatchMentor::query()->where('batch_id', $batch->id)->whereNotIn('user_id', $mentorIds ?: [0])->delete();
            foreach ($mentorIds as $mentorId) {
                BatchMentor::query()->updateOrCreate(
                    ['batch_id' => $batch->id, 'user_id' => $mentorId],
                    ['tenant_id' => $batch->tenant_id],
                );
            }
        });

        $this->notifyNewlyAllocated($batch->refresh(), $notify, $prevLead, $prevModuleTrainers, $prevMentors);

        return $this->moduleTrainers($batch);
    }

    /**
     * Message trainers/mentors who are newly allocated (vs the previous state).
     *
     * @param  array<int, int>  $prevModuleTrainers  module_id => user_id
     * @param  list<int>  $prevMentors
     */
    private function notifyNewlyAllocated(Batch $batch, NotifyBatchAllocation $notify, ?int $prevLead, array $prevModuleTrainers, array $prevMentors): void
    {
        $batch->load('moduleTrainers.module', 'batchMentors');

        // Group each trainer's newly-assigned module names.
        $newModulesByTrainer = [];
        foreach ($batch->moduleTrainers as $mt) {
            if (($prevModuleTrainers[$mt->module_id] ?? null) !== $mt->user_id) {
                $newModulesByTrainer[$mt->user_id][] = $mt->module?->name ?? 'a module';
            }
        }
        foreach ($newModulesByTrainer as $userId => $names) {
            $notify->send(User::query()->find($userId), $batch, 'teaching '.implode(', ', $names));
        }

        if ($batch->trainer_id !== null && $batch->trainer_id !== $prevLead) {
            $notify->send($batch->trainer, $batch, 'as the lead trainer');
        }

        foreach (array_diff($batch->batchMentors->pluck('user_id')->map(fn ($id) => (int) $id)->all(), $prevMentors) as $userId) {
            $notify->send(User::query()->find($userId), $batch, 'as a mentor');
        }
    }

    /**
     * @param  list<string>  $roles
     * @return list<array{id:int, name:string}>
     */
    private function staffOptions(array $roles): array
    {
        return User::query()
            ->where('user_type', 'staff')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', $roles))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * @param  list<string>  $roles
     * @return list<int>
     */
    private function staffIds(array $roles): array
    {
        return User::query()
            ->where('user_type', 'staff')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', $roles))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
