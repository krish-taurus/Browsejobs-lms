<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pause/defer request (PRD §6.20): freeze enrolment through a life event
 * and rejoin a later batch — the single biggest refund-dispute killer.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $batch_member_id
 * @property string $reason
 * @property string $status
 * @property int|null $decided_by
 * @property Carbon|null $paused_until
 * @property int|null $resume_batch_id
 */
class EnrolmentPause extends Model
{
    use BelongsToTenant;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RESUMED = 'resumed';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'batch_member_id', 'reason', 'status',
        'decided_by', 'decided_at', 'paused_until', 'resume_batch_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<BatchMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(BatchMember::class, 'batch_member_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'paused_until' => 'date'];
    }
}
