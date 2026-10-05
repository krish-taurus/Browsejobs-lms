<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerJobStatus;
use App\Enums\JdMockStatus;
use App\Jobs\GenerateJdMock;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/** PRD-E F2 — create a draft JD in a workspace. */
final readonly class CreateEmployerJob
{
    /** Still-open statuses a new JD can collide with — a closed JD is done, so reposting the same role later is a legitimate re-hire, not a duplicate. */
    private const ACTIVE_STATUSES = [
        EmployerJobStatus::Draft->value,
        EmployerJobStatus::Published->value,
        EmployerJobStatus::Paused->value,
    ];

    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(EmployerWorkspace $workspace, User $creator, array $attributes): EmployerJob
    {
        $this->guardAgainstDuplicate($workspace, $attributes);

        return app(TenantContext::class)->run($workspace->tenant, function () use ($workspace, $creator, $attributes): EmployerJob {
            $job = EmployerJob::create([
                ...$attributes,
                'employer_workspace_id' => $workspace->id,
                'created_by_id' => $creator->id,
                'status' => EmployerJobStatus::Draft->value,
            ]);

            // Generated from the draft, not on publish: an employer setting
            // up interview rounds before going live needs real questions to
            // suggest, not "still generating" every time. PublishEmployerJob
            // already guards its own mock creation on `! $job->mocks()->exists()`,
            // so publishing this JD later just uses what's generated here
            // instead of creating a second one — the trade-off is that a
            // draft abandoned before publish still cost one generation call.
            $mock = JdMock::create([
                'employer_job_id' => $job->id,
                'version' => 1,
                'status' => JdMockStatus::Pending->value,
            ]);
            GenerateJdMock::dispatch($mock->id);

            $this->audit->log('employer.job_created', $job, [
                'workspace_id' => $workspace->id,
                'title' => $job->title,
            ], $creator);

            return $job;
        });
    }

    /**
     * Same title + same experience band + same location, still open — that's
     * not a new role, it's the same JD posted again. Blocks before the job
     * (and its mock interview) is ever created, whether the request came from
     * the manual form or Neural Ops dictating one out loud.
     *
     * @param array<string, mixed> $attributes
     */
    private function guardAgainstDuplicate(EmployerWorkspace $workspace, array $attributes): void
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        if ($title === '') {
            return;
        }

        $minYears = $attributes['experience_min_years'] ?? 0;
        $maxYears = $attributes['experience_max_years'] ?? null;
        $locations = $this->normalizeLocations((array) ($attributes['locations'] ?? []));

        $candidates = EmployerJob::query()
            ->where('employer_workspace_id', $workspace->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($title)])
            ->where('experience_min_years', $minYears)
            ->when(
                $maxYears === null,
                fn ($q) => $q->whereNull('experience_max_years'),
                fn ($q) => $q->where('experience_max_years', $maxYears),
            )
            ->get();

        foreach ($candidates as $existing) {
            $existingLocations = $this->normalizeLocations((array) ($existing->locations ?? []));

            // No location stated on either side reads as "anywhere" — still
            // the same JD. Otherwise only a real overlap counts as a match.
            $sameLocation = $locations === [] || $existingLocations === []
                || array_intersect($locations, $existingLocations) !== [];

            if (! $sameLocation) {
                continue;
            }

            $years = $maxYears !== null ? "{$minYears}-{$maxYears} yrs" : "{$minYears}+ yrs";

            throw ValidationException::withMessages([
                'title' => "You already have a {$existing->status->value} \"{$existing->title}\" JD for {$years} in the same location (posted {$existing->created_at->diffForHumans()}). Edit or close that one instead of posting a duplicate.",
            ]);
        }
    }

    /** @param array<int, mixed> $locations @return array<int, string> */
    private function normalizeLocations(array $locations): array
    {
        return array_values(array_unique(array_map(
            static fn ($l) => mb_strtolower(trim((string) $l)),
            array_filter($locations, static fn ($l) => trim((string) $l) !== ''),
        )));
    }
}
