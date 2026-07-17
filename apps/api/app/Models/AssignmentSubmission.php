<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\GradeStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A student's submission for an assignment (PRD §6.5).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $assignment_id
 * @property int $user_id
 * @property string|null $body
 * @property string|null $link
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_mime
 * @property int|null $file_size
 * @property AssignmentSubmissionStatus $status
 * @property Carbon|null $submitted_at
 */
class AssignmentSubmission extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'assignment_id', 'user_id', 'body', 'link',
        'file_path', 'file_name', 'file_mime', 'file_size', 'status', 'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentSubmissionStatus::class,
            'file_size' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /** True once the trainer has released a grade to the student. */
    public function hasReleasedGrade(): bool
    {
        return $this->grade()->where('status', GradeStatus::Approved->value)->exists();
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasOne<AssignmentGrade, $this>
     */
    public function grade(): HasOne
    {
        return $this->hasOne(AssignmentGrade::class, 'submission_id');
    }
}
