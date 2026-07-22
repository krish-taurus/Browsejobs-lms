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

        // Include classes that have just started (still hostable) as well as future
        // ones, so the "Start class" button is available right around class time.
        $now = Carbon::now();
        $upcoming = LiveSession::query()
            ->whereIn('batch_id', $batchIds->all())
            ->whereIn('status', [LiveSessionStatus::Scheduled->value, LiveSessionStatus::Live->value])
            ->where('scheduled_start', '>=', $now->copy()->subHours(2))
            ->with(['batch:id,number,course_id', 'batch.course:id,code', 'topic:id,name,module_id'])
            ->orderBy('scheduled_start')
            ->limit(50)
            ->get()
            ->map(function (LiveSession $session) use ($userId, $batchModels, $now): array {
                $batch = $batchModels->get($session->batch_id);
                $teacher = $batch?->trainerForModule($session->topic?->module_id);
                $youTeach = $teacher !== null && (int) $teacher->id === $userId;

                // A teacher may go live as host from 15 min before start until 60 min
                // after end — mirrors StartLiveSession, which enforces it server-side.
                $start = $session->scheduled_start;
                $end = $session->scheduled_end ?? $start->copy()->addMinutes(intdiv($session->plannedSeconds(), 60));
                $canStart = $youTeach
                    && $session->zoom_start_url !== null
                    && $now->gte($start->copy()->subMinutes(15))
                    && $now->lte($end->copy()->addMinutes(60));

                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'batch_number' => $session->batch?->number,
                    'course_code' => $session->batch?->course?->code,
                    'topic' => $session->topic?->name,
                    'scheduled_start' => $session->scheduled_start->toIso8601String(),
                    'scheduled_end' => $session->scheduled_end?->toIso8601String(),
                    'status' => $session->status->value,
                    'you_teach' => $youTeach,
                    'can_start' => $canStart,
                    'join_url' => $session->zoom_join_url,
                ];
            });

        return response()->json(['data' => [
            'batches' => $batches->all(),
            'upcoming' => $upcoming->all(),
        ]]);
    }
}
