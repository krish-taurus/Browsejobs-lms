<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One firing of an automation rule against an application (PRD-E F6).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_automation_rule_id
 * @property int $employer_job_application_id
 * @property string $action
 * @property string $outcome
 * @property int|null $score_seen
 * @property Carbon $occurred_at
 */
final class EmployerAutomationRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employer_automation_rule_id',
        'employer_job_application_id',
        'action',
        'outcome',
        'score_seen',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerAutomationRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(EmployerAutomationRule::class, 'employer_automation_rule_id');
    }
}
