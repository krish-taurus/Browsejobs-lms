<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TestimonialStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student-submitted testimonial (PRD §5 Stage 3). Verified by an admin before
 * it publishes to the /reviews wall (writes a `reviews` row) and a voucher is
 * issued. Never fabricated — a row exists only because a student submitted it.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $batch_id
 * @property string|null $course_slug
 * @property string|null $stage
 * @property int $rating
 * @property string $body
 * @property string|null $video_path
 * @property bool $consent_publish
 * @property bool $followed_instagram
 * @property bool $subscribed_youtube
 * @property bool $posted_google_review
 * @property TestimonialStatus $status
 * @property int|null $review_id
 * @property int|null $voucher_issue_id
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $reject_reason
 */
class Testimonial extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'batch_id', 'course_slug', 'stage', 'rating', 'body',
        'video_path', 'consent_publish', 'followed_instagram', 'subscribed_youtube',
        'posted_google_review', 'status', 'review_id', 'voucher_issue_id',
        'reviewed_by', 'reviewed_at', 'reject_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'consent_publish' => 'boolean',
            'followed_instagram' => 'boolean',
            'subscribed_youtube' => 'boolean',
            'posted_google_review' => 'boolean',
            'status' => TestimonialStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<VoucherIssue, $this>
     */
    public function voucherIssue(): BelongsTo
    {
        return $this->belongsTo(VoucherIssue::class);
    }
}
