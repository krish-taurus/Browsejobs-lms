<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One student telemetry event (PRD §6.4).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property ActivityType $type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $value
 * @property Carbon $occurred_at
 */
class ActivityEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'subject_type', 'subject_id', 'value', 'meta', 'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'value' => 'integer',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
