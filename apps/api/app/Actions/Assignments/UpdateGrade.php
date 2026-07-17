<?php

declare(strict_types=1);

namespace App\Actions\Assignments;

use App\Models\AssignmentGrade;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * Trainer edits a grade's score/feedback/criteria (PRD §6.5). Re-clamps each criterion
 * to its rubric max and re-sums the overall score. Audited (grade change). May edit a
 * released grade (it stays released).
 */
final readonly class UpdateGrade
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  list<array{criterion: string, score: mixed, note?: mixed}>  $criteria
     */
    public function handle(AssignmentGrade $grade, string $feedback, array $criteria, ?User $actor = null): AssignmentGrade
    {
        return app(TenantContext::class)->run($grade->tenant, function () use ($grade, $feedback, $criteria, $actor): AssignmentGrade {
            $assignment = $grade->submission->assignment;

            $clean = [];
            $total = 0;
            foreach ($criteria as $row) {
                $name = trim((string) ($row['criterion'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $score = max(0, min((int) ($row['score'] ?? 0), $assignment->rubricMaxFor($name)));
                $total += $score;
                $clean[] = ['criterion' => $name, 'score' => $score, 'note' => trim((string) ($row['note'] ?? ''))];
            }

            $grade->update([
                'feedback' => $feedback,
                'criteria' => $clean,
                'score' => min($total, $assignment->max_points),
            ]);

            $this->audit->log(
                action: 'grade.updated',
                target: $grade,
                metadata: ['submission_id' => $grade->submission_id, 'score' => $grade->score],
                actor: $actor,
            );

            return $grade->refresh();
        });
    }
}
