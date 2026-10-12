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
 * @property list<array{name: string, source: string, match_score: int, matched_skills: list<string>}>|null $near_misses closest below the bar, kept only when nobody cleared it
 * @property list<array{candidate_key: string, name: string, source: string, cv_match: int, candidate_user_id: int|null, employer_candidate_id: int|null}>|null $awaiting CV matches with no AI interview yet — sent the AI screening interview
 */
final class JobShortlistRun extends Model
{
    use BelongsToTenant;

    public const TRIGGER_PUBLISH = 'publish';

    public const TRIGGER_MANUAL = 'manual';

    /** Someone finished the AI screening interview the agent sent them. */
    public const TRIGGER_SCREENING = 'screening';

    protected $fillable = [
        'tenant_id', 'employer_job_id', 'trigger', 'triggered_by_user_id', 'settings', 'considered', 'shortlisted_count', 'near_misses', 'awaiting',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'considered' => 'array',
            'near_misses' => 'array',
            'awaiting' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
