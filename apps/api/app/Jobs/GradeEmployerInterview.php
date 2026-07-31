<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewGraded;
use App\Models\EmployerInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Grades a submitted L1/L2 round against the JD rubric (PRD-E F5).
 *
 * Idempotent: only `submitted` rounds are processed. Deliberately NO
 * fabricated fallback — fake interview scores would poison employer
 * trust, so on AI failure the job retries and, if exhausted, the round
 * stays `submitted` and the employer UI shows "grading delayed"
 * honestly (PRD-E §11 graceful degradation).
 */
final class GradeEmployerInterview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 300];

    public function __construct(public readonly int $interviewId) {}

    public function handle(AiGateway $gateway): void
    {
        $interview = EmployerInterview::withoutGlobalScopes()->find($this->interviewId);
        if ($interview === null || $interview->status !== EmployerInterviewStatus::Submitted) {
            return;
        }

        $tenant = Tenant::query()->find($interview->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($gateway, $interview): void {
            $application = $interview->application()->withoutGlobalScopes()->first();
            $job = $application?->job()->withoutGlobalScopes()->first();
            $actor = $job !== null ? User::query()->find($job->created_by_id) : null;

            if ($application === null || $actor === null) {
                return;
            }

            $qa = collect($interview->question_set)->map(function (array $question): array {
                return ['id' => $question['id'] ?? null, 'question' => $question['text'] ?? ''];
            })->map(function (array $entry) use ($interview): array {
                $answer = collect($interview->answers ?? [])->firstWhere('question_id', $entry['id']);
                $entry['answer'] = is_array($answer) ? (string) ($answer['answer'] ?? '') : '';

                return $entry;
            });

            $result = $gateway->complete($actor, AiPurpose::Grading, 'employer_interview_grade', 1, [
                'rubric' => json_encode($interview->rubric, JSON_PRETTY_PRINT),
                'qa' => json_encode($qa->values()->all(), JSON_PRETTY_PRINT),
            ], ['max_tokens' => 1200]);

            $data = JsonOutput::object($result->text);

            if (! is_array($data) || ! is_array($data['dimension_scores'] ?? null) || ! is_numeric($data['overall_score'] ?? null)) {
                // Malformed output: throw so the queue retries; never invent scores.
                throw new RuntimeException('Employer interview grading returned malformed output.');
            }

            $interview->update([
                'status' => EmployerInterviewStatus::Graded->value,
                'dimension_scores' => array_map(fn ($v): int => max(0, min(100, (int) $v)), $data['dimension_scores']),
                'overall_score' => max(0, min(100, (int) $data['overall_score'])),
                'grading_summary' => is_string($data['summary'] ?? null) ? $data['summary'] : null,
                'grading_source' => 'ai',
                'graded_at' => now(),
            ]);

            EmployerInterviewGraded::dispatch($interview->fresh());
        });
    }
}
