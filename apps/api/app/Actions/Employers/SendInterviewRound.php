<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerInterviewStatus;
use App\Events\EmployerInterviewInvited;
use App\Models\EmployerInterview;
use App\Models\EmployerJobApplication;
use App\Models\EmployerJobRound;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Send one designed round to one candidate (PRD-E F18).
 *
 * This is the single path a round goes out by, whether an employer pressed
 * send or a threshold fired, so the invite, the snapshot and the audit entry
 * cannot drift apart between the two.
 *
 * Question selection is where this differs from the old behaviour. Every
 * round used to snapshot the whole JD mock, so a candidate who reached L2 was
 * asked the same questions they had already answered at L1. Here each round
 * draws the part of the bank that matches what the employer said it tests,
 * minus anything already asked of this candidate.
 */
final readonly class SendInterviewRound
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(
        EmployerJobApplication $application,
        EmployerJobRound $round,
        ?User $actor = null,
    ): EmployerInterview {
        return app(TenantContext::class)->run($application->tenant, function () use ($application, $round, $actor): EmployerInterview {
            if (! $round->isPlatformRun()) {
                throw ValidationException::withMessages([
                    'round' => "{$round->name} happens off the platform — record its outcome from the pipeline instead.",
                ]);
            }

            if (! $round->enabled) {
                throw ValidationException::withMessages([
                    'round' => "{$round->name} is switched off for this role.",
                ]);
            }

            $existing = EmployerInterview::query()
                ->where('employer_job_application_id', $application->id)
                ->where('round', $round->key)
                ->first();

            // Idempotent: a double-click, a retried job and a threshold that
            // fires twice must not send a second invite for the same round.
            if ($existing !== null) {
                return $existing;
            }

            $mock = $application->jdMock ?? $application->job?->currentMock();

            if ($mock === null || ! is_array($mock->questions) || $mock->questions === []) {
                throw ValidationException::withMessages([
                    'round' => 'This role has no interview questions yet. Generate the JD mock first.',
                ]);
            }

            $questions = $this->questionsFor($application, $round, $mock->questions);

            $interview = EmployerInterview::query()->create([
                'tenant_id' => $application->tenant_id,
                'employer_job_application_id' => $application->id,
                'employer_job_round_id' => $round->id,
                'round' => $round->key,
                // Snapshotted with the questions: renaming the round later
                // must not rewrite what the candidate was told they sat.
                'round_name' => $round->name,
                'status' => EmployerInterviewStatus::Invited->value,
                'question_set' => $questions,
                'rubric' => $this->rubricFor($round, $mock->rubric),
                'invited_at' => now(),
                'expires_at' => now()->addHours($round->window_hours),
            ]);

            $this->audit->log('employer.interview_round.sent', $interview, [
                'application_id' => $application->id,
                'round' => $round->key,
                'questions' => count($questions),
                'automatic' => $actor === null,
            ], $actor);

            EmployerInterviewInvited::dispatch($interview);

            return $interview;
        });
    }

    /**
     * The bank filtered to this round, preferring questions the candidate has
     * not already been asked.
     *
     * Two fallbacks, both deliberate:
     *
     *  - Focus matching nothing falls back to the whole remainder. An
     *    employer who names a skill the generator never wrote a question for
     *    should get an interview, not an error about their own config.
     *  - Nothing left unasked falls back to repeating. A small question bank
     *    would otherwise make the second round unsendable, which is worse
     *    than the repetition it avoids — and repetition is exactly what
     *    every round did before this existed. With a bank large enough to
     *    split (the generator will not accept fewer than five questions),
     *    the rounds genuinely differ.
     *
     * @param  list<array<string, mixed>>  $bank
     * @return list<array<string, mixed>>
     */
    private function questionsFor(EmployerJobApplication $application, EmployerJobRound $round, array $bank): array
    {
        $asked = EmployerInterview::query()
            ->where('employer_job_application_id', $application->id)
            ->pluck('question_set')
            ->flatMap(fn ($set): array => is_array($set) ? $set : [])
            ->map(fn ($q) => is_array($q) ? ($q['text'] ?? null) : null)
            // Collection::filter hands the callback (value, key), so the
            // first-class callable form would call is_string() with two args.
            ->filter(fn ($text): bool => is_string($text))
            ->all();

        $unasked = array_values(array_filter(
            $bank,
            fn ($q): bool => is_array($q) && is_string($q['text'] ?? null) && ! in_array($q['text'], $asked, true),
        ));

        if ($unasked === []) {
            $unasked = array_values(array_filter($bank, is_array(...)));
        }

        $focus = array_map(mb_strtolower(...), $round->focus_skills ?? []);
        $formats = array_keys(array_filter($round->format_mix ?? [], static fn (int $w): bool => $w > 0));

        $matched = array_values(array_filter($unasked, function (array $q) use ($focus, $formats): bool {
            $skillHit = $focus === [] || in_array(mb_strtolower((string) ($q['skill'] ?? '')), $focus, true);
            $formatHit = $formats === [] || in_array((string) ($q['type'] ?? ''), $formats, true);

            return $skillHit && $formatHit;
        }));

        $selected = $matched !== [] ? $matched : $unasked;

        return $round->question_count !== null
            ? array_slice($selected, 0, $round->question_count)
            : $selected;
    }

    /**
     * The round's own weighting when the employer set one, otherwise the
     * JD's rubric unchanged.
     *
     * @param  array<string, mixed>|null  $jdRubric
     * @return array<string, mixed>
     */
    private function rubricFor(EmployerJobRound $round, ?array $jdRubric): array
    {
        $weights = array_filter($round->competency_weights ?? [], static fn (int $w): bool => $w > 0);

        if ($weights === []) {
            return $jdRubric ?? ['dimensions' => []];
        }

        $total = array_sum($weights);
        $dimensions = [];

        /** @var array<int, array<string, mixed>> $jdDimensions */
        $jdDimensions = is_array($jdRubric['dimensions'] ?? null) ? $jdRubric['dimensions'] : [];

        foreach ($weights as $key => $weight) {
            $source = collect($jdDimensions)->firstWhere('key', $key);

            $dimensions[] = [
                'key' => $key,
                'label' => is_array($source) ? (string) ($source['label'] ?? $key) : $key,
                'weight' => (int) round($weight / $total * 100),
                'criteria' => is_array($source) && is_string($source['criteria'] ?? null)
                    ? $source['criteria']
                    : 'Judged against what this round was set up to test.',
            ];
        }

        return ['dimensions' => $dimensions];
    }
}
