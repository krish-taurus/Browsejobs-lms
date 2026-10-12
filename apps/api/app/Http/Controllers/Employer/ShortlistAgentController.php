<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\RunShortlistAgent;
use App\Actions\Employers\SendInterviewInvites;
use App\Enums\EmployerJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\UpdateShortlistSettingsRequest;
use App\Http\Resources\JobShortlistEntryResource;
use App\Models\EmployerCandidate;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\InterviewTemplates;
use App\Support\Employers\ResolvesMembership;
use App\Support\Employers\ShortlistSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The AI shortlist tab of a JD: the agent's settings (top N, minimum score,
 * which sources), its last run, and the people it picked. Anyone in the
 * workspace can look; only owners/recruiters can change settings, run it,
 * or take someone off the list.
 */
final class ShortlistAgentController extends Controller
{
    use ResolvesMembership;

    public function show(Request $request, EmployerWorkspace $workspace, EmployerJob $job): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        return response()->json(['data' => $this->payload($workspace, $job)]);
    }

    public function updateSettings(
        UpdateShortlistSettingsRequest $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        AuditLogger $audit,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $settings = ShortlistSettings::fromArray($request->validated());
        $job->update(['shortlist_settings' => $settings->toArray()]);

        $audit->log('employer.shortlist_settings_updated', $job, $settings->toArray(), $request->user());

        return response()->json(['data' => $this->payload($workspace, $job->fresh())]);
    }

    /** "Run now" — the client sees the result straight away (the matching is fast and free; no AI call). */
    public function run(Request $request, EmployerWorkspace $workspace, EmployerJob $job, RunShortlistAgent $agent): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_if($job->status === EmployerJobStatus::Closed, 422, 'This JD is closed.');

        $agent->handle($job, JobShortlistRun::TRIGGER_MANUAL, $request->user());

        return response()->json(['data' => $this->payload($workspace, $job->fresh())]);
    }

    public function remove(Request $request, EmployerWorkspace $workspace, EmployerJob $job, JobShortlistEntry $entry, AuditLogger $audit): JsonResponse
    {
        return $this->setStatus($request, $workspace, $job, $entry, $audit, JobShortlistEntry::STATUS_REMOVED);
    }

    public function restore(Request $request, EmployerWorkspace $workspace, EmployerJob $job, JobShortlistEntry $entry, AuditLogger $audit): JsonResponse
    {
        return $this->setStatus($request, $workspace, $job, $entry, $audit, JobShortlistEntry::STATUS_SHORTLISTED);
    }

    private function setStatus(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        JobShortlistEntry $entry,
        AuditLogger $audit,
        string $status,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($entry->employer_job_id === $job->id, 404);

        $removed = $status === JobShortlistEntry::STATUS_REMOVED;
        $entry->update([
            'status' => $status,
            'removed_by_user_id' => $removed ? $request->user()->id : null,
            'removed_at' => $removed ? now() : null,
        ]);

        $audit->log($removed ? 'employer.shortlist_entry_removed' : 'employer.shortlist_entry_restored', $entry, [
            'job_id' => $job->id,
            'candidate_key' => $entry->candidate_key,
        ], $request->user());

        return response()->json(['data' => $this->payload($workspace, $job)]);
    }

    /**
     * The message agent: WhatsApp everyone on the shortlist (or the chosen
     * entries) to pick a day for the first interview round.
     */
    public function invite(Request $request, EmployerWorkspace $workspace, EmployerJob $job, SendInterviewInvites $invites): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_if($job->status === EmployerJobStatus::Closed, 422, 'This JD is closed.');
        $validated = $request->validate(['entry_ids' => ['nullable', 'array'], 'entry_ids.*' => ['integer']]);

        $result = $invites->handle($job, $request->user(), $validated['entry_ids'] ?? null);

        return response()->json(['data' => [...$this->payload($workspace, $job), 'invite_result' => $result]]);
    }

    /** Submit the two WhatsApp templates the message agent needs (once). */
    public function submitTemplates(Request $request, EmployerWorkspace $workspace, EmployerJob $job, InterviewTemplates $templates, AuditLogger $audit): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $result = $templates->submitMissing();
        $audit->log('employer.interview_templates_submitted', $workspace, $result, $request->user());

        return response()->json(['data' => [...$this->payload($workspace, $job), 'template_result' => $result]]);
    }

    /** @return array<string, mixed> */
    private function payload(EmployerWorkspace $workspace, EmployerJob $job): array
    {
        $run = JobShortlistRun::query()
            ->where('employer_job_id', $job->id)
            ->with('triggeredBy:id,name')
            ->latest('id')
            ->first();

        $entries = JobShortlistEntry::query()
            ->where('employer_job_id', $job->id)
            ->with(['employerCandidate', 'candidateUser:id,name'])
            ->orderBy('rank')
            ->get();

        $counts = EmployerCandidate::query()
            ->where('employer_workspace_id', $workspace->id)
            ->selectRaw('source, count(*) as n')
            ->groupBy('source')
            ->pluck('n', 'source');

        // Each person's interview invite, found through their account's application for this JD.
        $userOf = $entries->mapWithKeys(fn (JobShortlistEntry $e) => [$e->id => $e->candidate_user_id ?? $e->employerCandidate?->user_id]);
        $invites = InterviewInvite::query()
            ->where('employer_job_id', $job->id)
            ->where('round', 'l1')
            ->get()
            ->keyBy('candidate_user_id');
        $inviteFor = function (JobShortlistEntry $e) use ($userOf, $invites): ?array {
            $invite = $invites->get($userOf[$e->id] ?? 0);

            return $invite === null ? null : [
                'status' => $invite->status,
                'day' => $invite->chosen_date !== null ? SendInterviewInvites::label($invite->chosen_date->toDateString()) : null,
                'error' => $invite->error,
            ];
        };
        $templates = app(InterviewTemplates::class)->statuses();

        return [
            'message_agent' => [
                'templates' => $templates,
                'ready' => ($templates[InterviewTemplates::INVITE] ?? null) === 'APPROVED',
                'invites' => InterviewInvite::query()->where('employer_job_id', $job->id)
                    ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            ],
            'settings' => ShortlistSettings::forJob($job)->toArray(),
            'source_counts' => [
                EmployerCandidate::SOURCE_CLIENT_DB => (int) ($counts[EmployerCandidate::SOURCE_CLIENT_DB] ?? 0),
                EmployerCandidate::SOURCE_NAUKRI => (int) ($counts[EmployerCandidate::SOURCE_NAUKRI] ?? 0),
            ],
            'last_run' => $run === null ? null : [
                'id' => $run->id,
                'trigger' => $run->trigger,
                'by' => $run->triggeredBy?->name,
                'considered' => $run->considered,
                'shortlisted_count' => $run->shortlisted_count,
                'settings' => $run->settings,
                'created_at' => $run->created_at?->toIso8601String(),
            ],
            'entries' => $entries->where('status', JobShortlistEntry::STATUS_SHORTLISTED)->values()
                ->map(fn (JobShortlistEntry $e) => [...(new JobShortlistEntryResource($e))->resolve(), 'invite' => $inviteFor($e)])
                ->all(),
            'removed' => JobShortlistEntryResource::collection(
                $entries->where('status', JobShortlistEntry::STATUS_REMOVED)->values()
            )->resolve(),
        ];
    }
}
