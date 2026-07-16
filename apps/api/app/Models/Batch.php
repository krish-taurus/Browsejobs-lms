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
        'starts_on', 'ends_on', 'linked_source_batch_id', 'status',
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
