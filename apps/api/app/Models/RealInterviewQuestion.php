<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One anonymised question in the real-interview bank (PRD §6.6). Only
 * `approved` rows feed mocks and gap reports; `asked_count` (bumped by the
 * dedupe stage) is the frequency signal behind bank analytics and Curriculum
 * Intelligence (§6.21).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $interview_transcript_id
 * @property int|null $course_id
 * @property string $role_title
 * @property string $question
 * @property string $normalized_question
 * @property string $fingerprint
 * @property list<string> $topic_tags
 * @property string|null $difficulty
 * @property string|null $round_type
 * @property list<string>|null $follow_ups
 * @property string|null $strong_answer
 * @property string|null $struggle_points
 * @property string|null $outcome
 * @property string $confidence
 * @property string $status
 * @property int $asked_count
 * @property Carbon|null $last_seen_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class RealInterviewQuestion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'interview_transcript_id', 'course_id', 'role_title',
        'question', 'normalized_question', 'fingerprint', 'topic_tags',
        'difficulty', 'round_type', 'follow_ups', 'strong_answer',
        'struggle_points', 'outcome', 'confidence', 'status', 'asked_count',
        'last_seen_at', 'approved_by', 'approved_at',
    ];

    public static function fingerprintFor(string $roleTitle, string $normalized): string
    {
        return sha1(mb_strtolower(trim($roleTitle)).'|'.mb_strtolower(trim($normalized)));
    }

    /**
     * @return BelongsTo<InterviewTranscript, $this>
     */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(InterviewTranscript::class, 'interview_transcript_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic_tags' => 'array',
            'follow_ups' => 'array',
            'asked_count' => 'integer',
            'last_seen_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
