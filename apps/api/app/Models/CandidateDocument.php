<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One document a candidate uploaded for verification (PRD-E F9).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $kind
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size_bytes
 * @property string $extract_status
 * @property string $review_status
 * @property array<string, mixed>|null $ai_verdict
 * @property Carbon|null $assessed_at
 * @property int|null $reviewed_by_id
 * @property Carbon|null $reviewed_at
 */
final class CandidateDocument extends Model
{
    use BelongsToTenant;

    /**
     * What a document claims to be. The list is closed because the assessment
     * prompt asks a different question of each — a payslip is checked for
     * internal consistency, an offer letter for the role and dates it states.
     */
    public const KINDS = [
        'offer_letter' => 'Offer letter',
        'payslip' => 'Payslip',
        'relieving_letter' => 'Relieving letter',
        'experience_letter' => 'Experience letter',
        'id_proof' => 'Government ID',
        'marksheet' => 'Marksheet or degree',
        'other' => 'Something else',
    ];

    public const EXTRACT_PENDING = 'pending';

    public const EXTRACT_DONE = 'extracted';

    /** We hold the file but cannot read its text — no extractor, no OCR. */
    public const EXTRACT_UNREADABLE = 'unreadable';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_ACCEPTED = 'accepted';

    public const REVIEW_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'user_id', 'kind', 'path', 'original_name', 'mime', 'size_bytes',
        'extract_status', 'review_status', 'ai_verdict', 'assessed_at',
        'reviewed_by_id', 'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_verdict' => 'array',
            'size_bytes' => 'integer',
            'assessed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? 'Document';
    }

    /**
     * What the reviewer should be told in one line.
     *
     * An unreadable file says so plainly. Silence would let "no AI concerns"
     * be read as "the AI looked and found nothing", when nothing was read.
     */
    public function assessmentSummary(): string
    {
        if ($this->extract_status === self::EXTRACT_UNREADABLE) {
            return 'Not machine-readable — open it yourself. No AI assessment was made.';
        }

        if ($this->assessed_at === null) {
            return 'Not assessed yet.';
        }

        $summary = $this->ai_verdict['summary'] ?? null;

        return is_string($summary) && $summary !== '' ? $summary : 'Assessed, with nothing to report.';
    }
}
