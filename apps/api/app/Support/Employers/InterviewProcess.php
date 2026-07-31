<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Models\EmployerJob;
use App\Models\EmployerJobRound;
use Illuminate\Support\Collection;

/**
 * Reads a JD's interview process, provisioning the two rounds every JD used
 * to run implicitly (PRD-E F18).
 *
 * Before F18 the process was hard-coded: entering the L1 stage created an L1
 * interview, entering L2 created an L2 one, and both snapshotted the same JD
 * mock — so the two rounds asked identical questions. Provisioning those two
 * as real, editable rows means existing JDs keep behaving exactly as they did
 * while becoming visible and changeable.
 */
final readonly class InterviewProcess
{
    /**
     * The rounds for a JD, creating the defaults on first read.
     *
     * @return Collection<int, EmployerJobRound>
     */
    public function forJob(EmployerJob $job): Collection
    {
        $rounds = $job->rounds()->get();

        if ($rounds->isNotEmpty()) {
            return $rounds;
        }

        foreach (EmployerJobRound::DEFAULTS as $default) {
            $job->rounds()->create([
                'tenant_id' => $job->tenant_id,
                'key' => $default['key'],
                'name' => $default['name'],
                'position' => $default['position'],
                'kind' => EmployerJobRound::KIND_AI,
                'question_count' => $default['question_count'],
                'window_hours' => (int) config('employers.interview_window_hours'),
                'dispatch' => EmployerJobRound::DISPATCH_MANUAL,
                'enabled' => true,
            ]);
        }

        return $job->rounds()->get();
    }

    /** The round a given key names, or null when the JD has no such round. */
    public function round(EmployerJob $job, string $key): ?EmployerJobRound
    {
        return $this->forJob($job)->firstWhere('key', $key);
    }

    /**
     * The next enabled round after the one given, in process order. Null when
     * the candidate has reached the end of the process — which is a real
     * answer, not an error.
     */
    public function next(EmployerJob $job, ?string $afterKey): ?EmployerJobRound
    {
        $rounds = $this->forJob($job)->filter(fn (EmployerJobRound $r): bool => $r->enabled)->values();

        if ($afterKey === null) {
            return $rounds->first();
        }

        $index = $rounds->search(fn (EmployerJobRound $r): bool => $r->key === $afterKey);

        return $index === false ? null : $rounds->get((int) $index + 1);
    }
}
