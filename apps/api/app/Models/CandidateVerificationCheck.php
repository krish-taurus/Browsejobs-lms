<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One verification result for one candidate (PRD-E F9).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property VerificationKind $kind
 * @property VerificationStatus $status
 * @property string|null $provider
 * @property string|null $provider_ref
 * @property array<string, mixed>|null $evidence
 * @property string|null $failure_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $expires_at
 */
class CandidateVerificationCheck extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'kind', 'status', 'provider', 'provider_ref',
        'evidence', 'failure_reason', 'reviewed_by_id',
        'submitted_at', 'verified_at', 'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => VerificationKind::class,
            'status' => VerificationStatus::class,
            'evidence' => 'array',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * A verified check that has passed its expiry no longer counts. Reading
     * it as effective-status rather than rewriting rows means an expiry
     * never needs a sweep job to be correct.
     */
    public function effectiveStatus(): VerificationStatus
    {
        if ($this->status === VerificationStatus::Verified
            && $this->expires_at !== null
            && $this->expires_at->isPast()) {
            return VerificationStatus::Expired;
        }

        return $this->status;
    }
}
