<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LiveSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchMentor;
use App\Models\BatchModuleTrainer;
use App\Models\LiveSession;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "My teaching" — a self-scoped view for a trainer or mentor: every batch they
 * are involved in (as lead trainer, a per-module trainer, or a mentor), the
 * modules they personally teach, and their upcoming classes across all batches.
 * No permission gate beyond staff auth; a person only ever sees their own work.
 */
final class MyTeachingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;

        // Every batch this person touches, and how.
        $leadBatchIds = Batch::query()->where('trainer_id', $userId)->pluck('id')->all();
        $moduleRows = BatchModuleTrainer::query()->where('user_id', $userId)->get(['batch_id', 'module_id']);
        $mentorBatchIds = BatchMentor::query()->where('user_id', $userId)->pluck('batch_id')->all();

        $batchIds = collect($leadBatchIds)
            ->merge($moduleRows->pluck('batch_id'))
            ->merge($mentorBatchIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($batchIds->isEmpty()) {
            return response()->json(['data' => ['batches' => [], 'upcoming' => []]]);
        }

        // Module names for the modules this person teaches.
        $myModuleIdsByBatch = $moduleRows->groupBy('batch_id')
            ->map(fn ($rows) => $rows->pluck('module_id')->map(fn ($id) => (int) $id)->all());
        $moduleNames = Module::query()
            ->whereIn('id', $moduleRows->pluck('module_id')->unique()->all())
            ->pluck('name', 'id');

        $batches = Batch::query()
            ->whereIn('id', $batchIds->all())
            ->with('course:id,code,name')
            ->orderByDesc('id')
            ->get()
            ->map(function (Batch $batch) use ($leadBatchIds, $mentorBatchIds, $myModuleIdsByBatch, $moduleNames): array {
                $roles = [];
                if (in_array($batch->id, $leadBatchIds, true)) {
                    $roles[] = 'Lead trainer';
                }
                $myModules = collect($myModuleIdsByBatch->get($batch->id, []))
                    ->map(fn (int $id) => $moduleNames[$id] ?? 'a module')
                    ->values()->all();
                if ($myModules !== []) {
                    $roles[] = 'Module trainer';
                }
                if (in_array($batch->id, $mentorBatchIds, true)) {
                    $roles[] = 'Mentor';
                }

                return [
                    'id' => $batch->id,
                    'number' => $batch->number,
                    'type' => $batch->type->value,
                    'status' => $batch->status,
                    'course' => $batch->course ? ['code' => $batch->course->code, 'name' => $batch->course->name] : null,
                    'roles' => $roles,
                    'modules' => $myModules,
                    'starts_on' => $batch->starts_on?->toDateString(),
                    'ends_on' => $batch->ends_on?->toDateString(),
                ];
            });

        // Upcoming classes across all their batches; mark the ones they personally teach.
        $batchModels = Batch::query()->whereIn('id', $batchIds->all())
            ->with(['trainer', 'moduleTrainers'])->get()->keyBy('id');

        $upcoming = LiveSession::query()
            ->whereIn('batch_id', $batchIds->all())
            ->where('status', LiveSessionStatus::Scheduled->value)
            ->where('scheduled_start', '>=', Carbon::now())
            ->with(['batch:id,number,course_id', 'batch.course:id,code', 'topic:id,name,module_id'])
            ->orderBy('scheduled_start')
            ->limit(50)
            ->get()
            ->map(function (LiveSession $session) use ($userId, $batchModels): array {
                $batch = $batchModels->get($session->batch_id);
                $teacher = $batch?->trainerForModule($session->topic?->module_id);

                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'batch_number' => $session->batch?->number,
                    'course_code' => $session->batch?->course?->code,
                    'topic' => $session->topic?->name,
                    'scheduled_start' => $session->scheduled_start->toIso8601String(),
                    'scheduled_end' => $session->scheduled_end?->toIso8601String(),
                    'you_teach' => $teacher !== null && (int) $teacher->id === $userId,
                    'join_url' => $session->zoom_join_url,
                ];
            });

        return response()->json(['data' => [
            'batches' => $batches->all(),
            'upcoming' => $upcoming->all(),
        ]]);
    }
}
