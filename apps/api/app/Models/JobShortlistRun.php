<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pass of the shortlist agent over a JD: the settings it used and how
 * many people it looked at in each source — the agent's own log line.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_id
 * @property string $trigger
 * @property int|null $triggered_by_user_id
 * @property array<string, mixed> $settings
 * @property array<string, array{considered: int, matched: int}> $considered
 * @property int $shortlisted_count
 */
final class JobShortlistRun extends Model
{
    use BelongsToTenant;

    public const TRIGGER_PUBLISH = 'publish';

    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'tenant_id', 'employer_job_id', 'trigger', 'triggered_by_user_id', 'settings', 'considered', 'shortlisted_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'considered' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
