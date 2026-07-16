<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Crm\AddLeadNote;
use App\Actions\Crm\AssignLead;
use App\Actions\Crm\CaptureLead;
use App\Actions\Crm\FindDuplicateLeads;
use App\Actions\Crm\ImportLeadsCsv;
use App\Actions\Crm\MergeLeads;
use App\Actions\Crm\MoveLeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AddNoteRequest;
use App\Http\Requests\Crm\AssignLeadRequest;
use App\Http\Requests\Crm\ImportLeadsRequest;
use App\Http\Requests\Crm\MergeLeadsRequest;
use App\Http\Requests\Crm\MoveStageRequest;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Resources\LeadResource;
use App\Http\Resources\LeadStageResource;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRM lead inbox + detail + pipeline actions (PRD §6.12). All routes are
 * gated by `can:manage-leads`; route-model binding is tenant-scoped so a foreign
 * tenant's ids 404.
 */
final class LeadAdminController extends Controller
{
    /** Filtered, paginated inbox. */
    public function index(Request $request): JsonResponse
    {
        $leads = Lead::query()
            ->whereNull('merged_into_id')
            ->with(['stage', 'assignee:id,name'])
            ->when($request->filled('lead_type'), fn ($q) => $q->where('lead_type', $request->string('lead_type')))
            ->when($request->filled('lead_stage_id'), fn ($q) => $q->where('lead_stage_id', $request->integer('lead_stage_id')))
            ->when($request->filled('course_slug'), fn ($q) => $q->where('course_slug', $request->string('course_slug')))
            ->when($request->filled('utm_source'), fn ($q) => $q->where('utm_source', $request->string('utm_source')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate(25);

        return LeadResource::collection($leads)->response();
    }

    /** All non-merged leads for the kanban board, plus the stage columns. */
    public function board(): JsonResponse
    {
        $stages = LeadStage::query()->orderBy('position')->get();
        $leads = Lead::query()
            ->whereNull('merged_into_id')
            ->with(['assignee:id,name'])
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return response()->json([
            'stages' => LeadStageResource::collection($stages),
            'leads' => LeadResource::collection($leads),
        ]);
    }

    /** Assignable counselors (staff whose roles grant manage-leads). */
    public function counselors(): JsonResponse
    {
        $users = User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'manage-leads'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $users]);
    }

    public function show(Lead $lead, FindDuplicateLeads $duplicates): JsonResponse
    {
        $lead->load([
            'stage',
            'assignee:id,name',
            'timelineEvents' => fn ($q) => $q->with('actor:id,name')->orderByDesc('occurred_at'),
            'tasks' => fn ($q) => $q->with('assignee:id,name')->orderBy('completed_at')->orderBy('due_at'),
        ]);
        $lead->setAttribute('duplicates', $duplicates->handle($lead));

        return (new LeadResource($lead))->response();
    }

    public function store(StoreLeadRequest $request, CaptureLead $capture): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $lead = $capture->handle($tenant, [
            ...$request->validated(),
            'page' => 'admin_manual',
            'consent_version' => 'manual',
        ]);

        return (new LeadResource($lead->load('stage', 'assignee:id,name')))->response()->setStatusCode(201);
    }

    public function import(ImportLeadsRequest $request, ImportLeadsCsv $import): JsonResponse
    {
        $tenant = app(TenantContext::class)->get();

        $csv = $request->filled('csv')
            ? (string) $request->string('csv')
            : (string) $request->file('file')?->get();

        $summary = $import->handle($tenant, $csv);

        return response()->json(['data' => $summary]);
    }

    public function assign(AssignLeadRequest $request, Lead $lead, AssignLead $assign): JsonResponse
    {
        $counselor = User::query()->findOrFail($request->integer('counselor_id'));
        $assign->handle($lead, $counselor, $request->user());

        return (new LeadResource($lead->fresh(['stage', 'assignee:id,name'])))->response();
    }

    public function moveStage(MoveStageRequest $request, Lead $lead, MoveLeadStage $move): JsonResponse
    {
        $stage = LeadStage::query()->findOrFail($request->integer('lead_stage_id'));
        $move->handle($lead, $stage, $request->user());

        return (new LeadResource($lead->fresh(['stage', 'assignee:id,name'])))->response();
    }

    public function merge(MergeLeadsRequest $request, Lead $lead, MergeLeads $merge): JsonResponse
    {
        $loser = Lead::query()->findOrFail($request->integer('loser_id'));
        $survivor = $request->filled('survivor_id')
            ? Lead::query()->findOrFail($request->integer('survivor_id'))
            : null;

        $result = $merge->handle($lead, $loser, $survivor, $request->user());

        return (new LeadResource($result->load('stage', 'assignee:id,name')))->response();
    }

    public function note(AddNoteRequest $request, Lead $lead, AddLeadNote $note): JsonResponse
    {
        $note->handle($lead, (string) $request->string('body'), $request->user());

        return response()->json(['ok' => true]);
    }
}
