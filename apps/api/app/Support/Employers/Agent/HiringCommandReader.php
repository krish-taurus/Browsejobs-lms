<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Enums\AiPurpose;
use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Employers\InterviewProcess;
use App\Support\Taurus\BrainStatus;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Reads what a recruiter said to Taurus — typed, a WhatsApp voice note, or
 * spoken in the web app — into a structured instruction.
 *
 * The model only reads. It never picks who is affected: it hands back the
 * role, the round and the names as they were said, and HiringActionPlanner
 * resolves those against the pipeline itself. Candidate names never go into
 * the prompt.
 */
final readonly class HiringCommandReader
{
    public const INTENTS = ['act', 'question', 'post_job', 'other'];

    public const ACTIONS = ['start_round', 'move_stage', 'reject', 'publish_job', 'pause_job', 'close_job', 'retake_round', 'send_report', 'update_job'];

    private const STAGES = ['shortlisted', 'l1', 'l2', 'human_round', 'offer'];

    private const TARGETS = ['human_round', 'offer', 'hired'];

    public function __construct(
        private AiGateway $ai,
        private BrainStatus $brain,
        private InterviewProcess $process,
    ) {}

    /**
     * Null when the model could not be reached or answered in the wrong shape;
     * the caller then falls back to what it did before agentic hiring.
     *
     * @return array{intent: string, action: string|null, job_id: int|null, round: string|null, names: list<string>, top_n: int|null, from_stage: string|null, to_stage: string|null, reason: string|null, reply: string}|null
     */
    public function read(User $user, EmployerWorkspace $workspace, string $said, ?EmployerJob $scope = null): ?array
    {
        $said = trim($said);
        if ($said === '') {
            return null;
        }

        $jobs = $this->jobs($workspace);
        $active = $this->brain->active();

        try {
            $result = $this->ai->complete($user, AiPurpose::Taurus, 'hiring_command', 5, [
                'jobs' => $this->describe($jobs),
                'scope' => $scope !== null ? "They are looking at the role with id {$scope->id} ({$scope->title}); a request that names no role means this one." : '',
                'said' => mb_substr($said, 0, 1200),
            ], ['max_tokens' => 300, ...($active !== null ? BrainStatus::gatewayOptions($active) : [])]);
            $parsed = JsonOutput::object($result->text);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($parsed === null) {
            return null;
        }

        $intent = JsonOutput::string($parsed, 'intent');
        if (! in_array($intent, self::INTENTS, true)) {
            return null;
        }

        $action = JsonOutput::string($parsed, 'action');
        $jobId = JsonOutput::positiveInt($parsed, 'job');
        $round = strtolower(JsonOutput::string($parsed, 'round'));
        $from = JsonOutput::string($parsed, 'from_stage');
        $to = JsonOutput::string($parsed, 'to_stage');
        $topN = JsonOutput::positiveInt($parsed, 'top_n');
        $reason = JsonOutput::string($parsed, 'reason');

        return [
            'intent' => $intent,
            'action' => $intent === 'act' && in_array($action, self::ACTIONS, true) ? $action : null,
            // Only a role the model was shown; anything else is a guess.
            'job_id' => $jobId !== null && $jobs->contains('id', $jobId) ? $jobId : null,
            'round' => preg_match('/^[a-z0-9_-]{1,20}$/', $round) === 1 ? $round : null,
            'names' => $this->names($parsed['names'] ?? []),
            'top_n' => $topN !== null && $topN <= 100 ? $topN : null,
            'from_stage' => in_array($from, self::STAGES, true) ? $from : null,
            'to_stage' => in_array($to, self::TARGETS, true) ? $to : null,
            'reason' => $reason !== '' && mb_strlen($reason) <= 300 ? $reason : null,
            'reply' => mb_substr(JsonOutput::string($parsed, 'reply'), 0, 300),
        ];
    }

    /** @return Collection<int, EmployerJob> */
    private function jobs(EmployerWorkspace $workspace): Collection
    {
        return EmployerJob::query()
            ->where('employer_workspace_id', $workspace->id)
            ->where('status', '!=', EmployerJobStatus::Closed->value)
            ->latest('id')
            ->limit(25)
            ->get(['id', 'tenant_id', 'employer_workspace_id', 'title', 'status']);
    }

    /** @param  Collection<int, EmployerJob>  $jobs */
    private function describe(Collection $jobs): string
    {
        if ($jobs->isEmpty()) {
            return 'They have no open roles yet.';
        }

        $counts = EmployerJobApplication::query()
            ->whereIn('employer_job_id', $jobs->pluck('id'))
            ->selectRaw('employer_job_id, stage, count(*) as n')
            ->groupBy('employer_job_id', 'stage')
            ->toBase() // plain rows: the stage stays a string
            ->get()
            ->groupBy('employer_job_id');

        return $jobs->map(function (EmployerJob $job) use ($counts): string {
            $stages = collect($counts->get($job->id, []))
                ->map(fn ($row) => "{$row->stage} {$row->n}")
                ->implode(', ');
            $rounds = $this->process->forJob($job)
                ->filter(fn ($r) => $r->enabled)
                ->map(fn ($r) => $r->key.($r->isPlatformRun() ? ' (AI)' : ' (human)'))
                ->implode(', ');

            return "- id {$job->id}: {$job->title} ({$job->status->value})"
                .($stages !== '' ? " · candidates: {$stages}" : ' · no candidates yet')
                .($rounds !== '' ? " · rounds: {$rounds}" : '');
        })->implode("\n");
    }

    /** @return list<string> */
    private function names(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $names = [];
        foreach ($value as $name) {
            if (is_string($name) && ($clean = trim($name)) !== '' && mb_strlen($clean) <= 60) {
                $names[] = $clean;
            }
        }

        return array_values(array_slice(array_unique($names), 0, 20));
    }
}
