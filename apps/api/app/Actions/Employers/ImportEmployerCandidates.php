<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\EmployerCandidate;
use App\Models\EmployerCandidateImport;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\CandidateSheetParser;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Loads a client's candidate sheet (their database, or a Naukri export) into
 * the workspace's own candidate pool for the shortlist agent.
 *
 * Re-importing is safe: a person already in the pool for that source (same
 * phone, else same email) is updated, not duplicated. Rows without a name,
 * or with neither a phone nor an email, are skipped and reported — the agent
 * could rank them but nobody could ever contact them.
 */
final readonly class ImportEmployerCandidates
{
    private const MAX_REPORTED_ERRORS = 50;

    public function __construct(
        private CandidateSheetParser $parser,
        private AuditLogger $audit,
    ) {}

    public function handle(EmployerWorkspace $workspace, string $source, UploadedFile $file, User $actor): EmployerCandidateImport
    {
        try {
            $rows = $this->parser->parse((string) $file->getRealPath(), (string) $file->getClientOriginalExtension());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The sheet has a header but no candidate rows.']);
        }

        return app(TenantContext::class)->run($workspace->tenant, function () use ($workspace, $source, $file, $actor, $rows): EmployerCandidateImport {
            $import = EmployerCandidateImport::create([
                'tenant_id' => $workspace->tenant_id,
                'employer_workspace_id' => $workspace->id,
                'source' => $source,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'rows_total' => count($rows),
                'imported_by_user_id' => $actor->id,
            ]);

            $created = $updated = $skipped = 0;
            $errors = [];

            DB::transaction(function () use ($workspace, $source, $import, $rows, &$created, &$updated, &$skipped, &$errors): void {
                foreach ($rows as $row) {
                    $reason = match (true) {
                        $row['name'] === '' => 'no name',
                        $row['phone'] === null && $row['email'] === null => 'no mobile number or email',
                        default => null,
                    };
                    if ($reason !== null) {
                        $skipped++;
                        if (count($errors) < self::MAX_REPORTED_ERRORS) {
                            $errors[] = "Row {$row['row']}: {$reason}";
                        }

                        continue;
                    }

                    $key = $row['phone'] !== null ? 'p:'.$row['phone'] : 'e:'.$row['email'];

                    $candidate = EmployerCandidate::query()->firstOrNew([
                        'employer_workspace_id' => $workspace->id,
                        'source' => $source,
                        'dedupe_key' => $key,
                    ]);
                    $isNew = ! $candidate->exists;

                    // Blank cells never wipe what an earlier import already knew.
                    $fields = array_filter([
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                        'current_title' => $row['current_title'],
                        'current_company' => $row['current_company'],
                        'location' => $row['location'],
                        'experience_years' => $row['experience_years'],
                        'notice_period' => $row['notice_period'],
                        'current_ctc' => $row['current_ctc'],
                        'expected_ctc' => $row['expected_ctc'],
                        'education' => $row['education'],
                        'skills' => $row['skills'] !== [] ? $row['skills'] : null,
                        'summary' => $row['summary'],
                    ], fn ($v) => $v !== null);

                    $candidate->fill([
                        ...$fields,
                        'tenant_id' => $workspace->tenant_id,
                        'employer_candidate_import_id' => $import->id,
                    ])->save();

                    $isNew ? $created++ : $updated++;
                }
            });

            $import->update([
                'created_count' => $created,
                'updated_count' => $updated,
                'skipped_count' => $skipped,
                'errors' => $errors === [] ? null : $errors,
            ]);

            $this->audit->log('employer.candidates_imported', $import, [
                'workspace_id' => $workspace->id,
                'source' => $source,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ], $actor);

            return $import->fresh();
        });
    }
}
