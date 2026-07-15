<?php

declare(strict_types=1);

namespace App\Actions\Roster;

use App\Models\Batch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Bulk roster intake from CSV text with a `name,phone,email` header. Each valid
 * row resolves-or-creates a student and adds them to the batch; invalid or
 * duplicate rows are collected and reported rather than aborting the import.
 *
 * @phpstan-type ImportSummary array{imported: int, skipped: list<array{row: int, reason: string}>}
 */
final readonly class ImportRosterCsv
{
    public function __construct(
        private FindOrCreateStudent $findOrCreateStudent,
        private AddStudentToBatch $addStudentToBatch,
    ) {}

    /**
     * @return ImportSummary
     */
    public function handle(Batch $batch, Tenant $tenant, string $csv, ?User $actor = null): array
    {
        $rows = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $csv) ?: [])));

        if ($rows === []) {
            return ['imported' => 0, 'skipped' => []];
        }

        // Drop the header row if present.
        if (str_contains(strtolower($rows[0]), 'name') && str_contains(strtolower($rows[0]), 'phone')) {
            array_shift($rows);
        }

        $imported = 0;
        $skipped = [];

        foreach ($rows as $index => $line) {
            $rowNumber = $index + 1;
            [$name, $phone, $email] = array_pad(array_map('trim', explode(',', $line)), 3, '');

            if ($name === '') {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Missing name.'];

                continue;
            }

            try {
                $student = $this->findOrCreateStudent->handle($tenant, $name, $email, $phone);
                $this->addStudentToBatch->handle($batch, $student, actor: $actor);
                $imported++;
            } catch (ValidationException $e) {
                $skipped[] = ['row' => $rowNumber, 'reason' => collect($e->errors())->flatten()->first() ?? 'Invalid row.'];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
