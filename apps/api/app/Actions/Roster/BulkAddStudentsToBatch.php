<?php

declare(strict_types=1);

namespace App\Actions\Roster;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Bulk roster add from a multi-selected set of existing students (e.g. bootcamp
 * attendees or a lead segment). Already-present or over-capacity students are
 * skipped and reported; the rest are added.
 *
 * @phpstan-type BulkSummary array{added: int, skipped: list<array{user_id: int, reason: string}>}
 */
final readonly class BulkAddStudentsToBatch
{
    public function __construct(private AddStudentToBatch $addStudentToBatch) {}

    /**
     * @param  Collection<int, User>  $students
     * @return BulkSummary
     */
    public function handle(Batch $batch, Collection $students, ?User $actor = null): array
    {
        $added = 0;
        $skipped = [];

        foreach ($students as $student) {
            try {
                $this->addStudentToBatch->handle($batch, $student, actor: $actor);
                $added++;
            } catch (ValidationException $e) {
                $skipped[] = [
                    'user_id' => $student->id,
                    'reason' => collect($e->errors())->flatten()->first() ?? 'Could not add.',
                ];
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }
}
