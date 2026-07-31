<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\EmployerJob;
use App\Models\EmployerJobRound;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\InterviewProcess;
use App\Support\Employers\MockDesign;
use App\Support\Roles\RoleTaxonomy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replace a JD's interview process with the one the employer just designed
 * (PRD-E F18).
 *
 * Two rules keep this from quietly destroying evidence:
 *
 *  - A round that has already sent interviews is never deleted, only
 *    disabled. Dropping it would orphan the record of what a candidate was
 *    asked and how they scored.
 *  - A round's `key` is assigned once and never rewritten. The key is what
 *    ties an interview row to its definition, so renaming "L1 — role fit"
 *    to "Technical screen" keeps the history attached instead of forking it.
 */
final readonly class SaveInterviewProcess
{
    public function __construct(
        private RoleTaxonomy $taxonomy,
        private InterviewProcess $process,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rounds
     * @return list<EmployerJobRound>
     */
    public function handle(EmployerJob $job, array $rounds, User $actor): array
    {
        return DB::transaction(function () use ($job, $rounds, $actor): array {
            // Provision the defaults first. A client that saves before it
            // ever reads would otherwise not find `l1` to update, silently
            // fork a second round from its name, and leave the original
            // behind — so the JD would end up running both.
            $existing = $this->process->forJob($job)->keyBy('key');
            $seen = [];
            $saved = [];

            foreach (array_values($rounds) as $position => $raw) {
                $round = $this->upsert($job, $existing, $raw, $position + 1);
                $seen[] = $round->key;
                $saved[] = $round;
            }

            foreach ($existing as $key => $round) {
                if (in_array($key, $seen, true)) {
                    continue;
                }

                // Interviews already sent from this round are evidence. The
                // round leaves the process, but the rows keep their anchor.
                if ($round->interviews()->exists()) {
                    $round->update(['enabled' => false, 'dispatch' => EmployerJobRound::DISPATCH_MANUAL]);

                    continue;
                }

                $round->delete();
            }

            $this->audit->log('employer.interview_process.saved', $job, [
                'rounds' => array_map(
                    static fn (EmployerJobRound $r): array => ['key' => $r->key, 'kind' => $r->kind, 'dispatch' => $r->dispatch],
                    $saved,
                ),
            ], $actor);

            return $saved;
        });
    }

    /**
     * @param  Collection<string, EmployerJobRound>  $existing
     * @param  array<string, mixed>  $raw
     */
    private function upsert(EmployerJob $job, $existing, array $raw, int $position): EmployerJobRound
    {
        $key = is_string($raw['key'] ?? null) ? $raw['key'] : null;
        $round = $key !== null ? $existing->get($key) : null;

        $kind = in_array($raw['kind'] ?? null, EmployerJobRound::KINDS, true)
            ? (string) $raw['kind']
            : EmployerJobRound::KIND_AI;

        $dispatch = in_array($raw['dispatch'] ?? null, EmployerJobRound::DISPATCHES, true)
            ? (string) $raw['dispatch']
            : EmployerJobRound::DISPATCH_MANUAL;

        // A human round is a call someone schedules. Marking it automatic
        // would promise the platform sends something it cannot send.
        if ($kind === EmployerJobRound::KIND_HUMAN) {
            $dispatch = EmployerJobRound::DISPATCH_MANUAL;
        }

        $threshold = isset($raw['auto_min_score']) && $raw['auto_min_score'] !== null
            ? max(0, min(100, (int) $raw['auto_min_score']))
            : null;

        // Reuses the mock designer's vocabulary filter, so a round cannot
        // carry a competency key the grading prompt has never heard of.
        $design = MockDesign::fromArray([
            'focus_skills' => $raw['focus_skills'] ?? [],
            'competency_weights' => $raw['competency_weights'] ?? [],
            'format_mix' => $raw['format_mix'] ?? [],
            'question_count' => $raw['question_count'] ?? null,
            'notes' => $raw['notes'] ?? null,
        ], $this->taxonomy);

        $attributes = [
            'position' => $position,
            'name' => mb_substr(trim((string) ($raw['name'] ?? 'Round')), 0, 120) ?: 'Round',
            'kind' => $kind,
            'focus_skills' => $design->focusSkills,
            'competency_weights' => $design->competencyWeights,
            'format_mix' => $design->formatMix,
            'question_count' => $design->questionCount,
            'notes' => $design->notes,
            'window_hours' => max(1, min(720, (int) ($raw['window_hours'] ?? config('employers.interview_window_hours')))),
            'dispatch' => $dispatch,
            'auto_min_score' => $dispatch === EmployerJobRound::DISPATCH_AUTO ? $threshold : null,
            'enabled' => (bool) ($raw['enabled'] ?? true),
        ];

        if ($round !== null) {
            $round->update($attributes);

            return $round->refresh();
        }

        return $job->rounds()->create($attributes + [
            'tenant_id' => $job->tenant_id,
            'key' => $this->freshKey($job, $attributes['name']),
        ]);
    }

    /**
     * A short, stable key derived from the name, uniquified per JD. The name
     * can change afterwards; this cannot.
     */
    private function freshKey(EmployerJob $job, string $name): string
    {
        $base = mb_substr(Str::slug($name, '_') ?: 'round', 0, 24);
        $key = $base;
        $n = 2;

        while ($job->rounds()->where('key', $key)->exists()) {
            $key = mb_substr($base, 0, 24 - mb_strlen((string) $n) - 1)."_{$n}";
            $n++;
        }

        return $key;
    }
}
