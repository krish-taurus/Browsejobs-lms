<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GradeStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The grade for a submission (PRD §6.5). AI-drafted or manual; released to the student
 * only when `status` is approved. `ai_likelihood` is trainer-only.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $submission_id
 * @property GradeStatus $status
 * @property int $score
 * @property int $max_points
 * @property string|null $feedback
 * @property array<int, array{criterion: string, score: int, note: string}>|null $criteria
 * @property int|null $ai_likelihood
 * @property int|null $ai_event_id
 * @property int|null $graded_by
 * @property Carbon|null $approved_at
 */
class AssignmentGrade extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'submission_id', 'status', 'score', 'max_points', 'feedback',
        'criteria', 'ai_likelihood', 'ai_event_id', 'graded_by', 'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GradeStatus::class,
            'score' => 'integer',
            'max_points' => 'integer',
            'criteria' => 'array',
            'ai_likelihood' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AssignmentSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
