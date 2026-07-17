<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mentor's public face (PRD §6.11): who they are, what they cover, and
 * their live rating (average of student session ratings).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $bio
 * @property list<string> $expertise_tags
 * @property bool $is_active
 * @property string|null $google_calendar_id
 */
class MentorProfile extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'headline', 'bio', 'expertise_tags', 'is_active', 'google_calendar_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MentorAvailability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(MentorAvailability::class);
    }

    /**
     * @return HasMany<MentorAvailabilityException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(MentorAvailabilityException::class);
    }

    /**
     * @return HasMany<MentorSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(MentorSession::class);
    }

    public function averageRating(): ?float
    {
        $avg = $this->sessions()->whereNotNull('student_rating')->avg('student_rating');

        return $avg === null ? null : round((float) $avg, 1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expertise_tags' => 'array', 'is_active' => 'boolean'];
    }
}
