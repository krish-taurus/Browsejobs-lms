<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Crm\CompleteTask;
use App\Actions\Crm\CreateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreTaskRequest;
use App\Http\Resources\CrmTaskResource;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Counselor task queue (PRD §6.12). Defaults to the signed-in counselor's own
 * tasks ("my tasks"); admins can pass ?assigned_to / ?scope=all.
 */
final class CrmTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = CrmTask::query()
            ->with(['lead:id,name,phone', 'assignee:id,name'])
            ->when($request->query('scope') !== 'all', function ($q) use ($request) {
                $assignee = $request->integer('assigned_to') ?: $request->user()?->id;
                $q->where('assigned_to', $assignee);
            })
            ->when(! $request->boolean('include_done'), fn ($q) => $q->whereNull('completed_at'))
            ->orderByRaw('completed_at is null desc')
            ->orderBy('due_at')
            ->get();

        return CrmTaskResource::collection($tasks)->response();
    }

    public function store(StoreTaskRequest $request, CreateTask $create): JsonResponse
    {
        $lead = Lead::query()->findOrFail($request->integer('lead_id'));
        $assignee = $request->filled('assigned_to')
            ? User::query()->findOrFail($request->integer('assigned_to'))
            : $request->user();

        $task = $create->handle(
            $lead,
            $assignee,
            (string) $request->string('title'),
            $request->filled('due_at') ? Carbon::parse($request->string('due_at')->toString()) : null,
            $request->user(),
        );

        return (new CrmTaskResource($task->load('lead:id,name,phone', 'assignee:id,name')))->response()->setStatusCode(201);
    }

    public function complete(CrmTask $task, CompleteTask $complete): JsonResponse
    {
        $complete->handle($task);

        return (new CrmTaskResource($task->load('lead:id,name,phone', 'assignee:id,name')))->response();
    }
}
