<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\StoreAutomationRuleRequest;
use App\Http\Resources\EmployerAutomationRuleResource;
use App\Models\EmployerAutomationRule;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AutomationRuleController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, EmployerWorkspace $workspace, EmployerJob $job): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $rules = EmployerAutomationRule::query()
            ->where('employer_job_id', $job->id)
            ->withCount('runs')
            ->latest()
            ->get();

        return EmployerAutomationRuleResource::collection($rules)->response();
    }

    public function store(
        StoreAutomationRuleRequest $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        AuditLogger $audit,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $rule = EmployerAutomationRule::create([
            ...$request->validated(),
            'employer_job_id' => $job->id,
            'created_by_id' => $request->user()->id,
        ]);

        // Simulation (PRD-E F6): how many current applications would this
        // rule have advanced, so the employer sees impact before relying on it.
        $wouldMatch = EmployerJobApplication::query()
            ->where('employer_job_id', $job->id)
            ->whereNotNull('mock_score')
            ->where('mock_score', '>=', $rule->min_score)
            ->count();
        $rule->would_match = $wouldMatch;

        $audit->log('employer.automation_rule_created', $rule, [
            'job_id' => $job->id,
            'trigger' => $rule->trigger,
            'min_score' => $rule->min_score,
        ], $request->user());

        return (new EmployerAutomationRuleResource($rule))->response()->setStatusCode(201);
    }

    public function toggle(Request $request, EmployerWorkspace $workspace, EmployerJob $job, EmployerAutomationRule $rule, AuditLogger $audit): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($rule->employer_job_id === $job->id, 404);

        $rule->update(['enabled' => ! $rule->enabled]);

        $audit->log('employer.automation_rule_toggled', $rule, [
            'enabled' => $rule->enabled,
        ], $request->user());

        return (new EmployerAutomationRuleResource($rule->fresh()))->response();
    }

    public function destroy(Request $request, EmployerWorkspace $workspace, EmployerJob $job, EmployerAutomationRule $rule, AuditLogger $audit): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($rule->employer_job_id === $job->id, 404);

        $rule->delete();

        $audit->log('employer.automation_rule_deleted', null, [
            'job_id' => $job->id,
            'rule_id' => $rule->id,
        ], $request->user());

        return response()->json(['ok' => true]);
    }
}
