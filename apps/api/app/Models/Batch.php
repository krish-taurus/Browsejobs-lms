<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $course_id
 * @property string $number
 * @property BatchType $type
 * @property int|null $capacity
 * @property string $status
 */
class Batch extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'number', 'type', 'capacity',
        'starts_on', 'ends_on', 'linked_source_batch_id', 'status', 'trainer_id',
    ];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<BatchMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(BatchMember::class);
    }

    /**
     * @return HasMany<LiveSession, $this>
     */
    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    /**
     * The trainer assigned to this batch (PRD §6.13 Training ticket routing).
     *
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * The bootcamp this paid batch draws its attendees from (Stage 3).
     *
     * @return BelongsTo<Batch, $this>
     */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'linked_source_batch_id');
    }

    /**
     * The paid batch fed by this bootcamp (reverse of sourceBatch).
     *
     * @return HasOne<Batch, $this>
     */
    public function linkedPaidBatch(): HasOne
    {
        return $this->hasOne(Batch::class, 'linked_source_batch_id');
    }

    /**
     * Seats currently taken (excludes dropped/transferred members).
     */
    public function occupiedSeats(): int
    {
        return $this->members()
            ->whereIn('status', array_map(fn ($s) => $s->value, BatchMemberStatus::occupying()))
            ->count();
    }

    public function hasCapacityFor(int $additional = 1): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->occupiedSeats() + $additional <= $this->capacity;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BatchType::class,
            'capacity' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
