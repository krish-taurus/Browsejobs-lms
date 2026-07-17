<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A course-completion certificate (PRD §6.5).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $course_id
 * @property string $code
 * @property string $number
 * @property string $title
 * @property Carbon|null $issued_at
 * @property string|null $storage_path
 * @property string $status
 * @property Carbon|null $revoked_at
 * @property int|null $issued_by
 */
class Certificate extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RENDERED = 'rendered';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'course_id', 'code', 'number', 'title',
        'issued_at', 'storage_path', 'status', 'revoked_at', 'issued_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** Genuine + still valid: rendered and not revoked. */
    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->status === self::STATUS_RENDERED;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
