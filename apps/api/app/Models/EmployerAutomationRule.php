<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerApplicationStage;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployerAutomationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A per-JD automation rule (PRD-E F6).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_id
 * @property int $created_by_id
 * @property string $trigger
 * @property string|null $round
 * @property int $min_score
 * @property string $action
 * @property string|null $target_stage
 * @property bool $enabled
 */
final class EmployerAutomationRule extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployerAutomationRuleFactory> */
    use HasFactory;

    public const TRIGGER_APPLICATION_GRADED = 'application_graded';

    public const TRIGGER_INTERVIEW_GRADED = 'interview_graded';

    public const ACTION_ADVANCE = 'advance';

    public const ACTION_PARK = 'park';

    /**
     * The only stages automation may advance into (PRD-E F6 guardrail).
     * Never terminal, never offer, never human_round.
     */
    public const ALLOWED_TARGET_STAGES = [
        EmployerApplicationStage::Shortlisted->value,
        EmployerApplicationStage::L1->value,
        EmployerApplicationStage::L2->value,
    ];

    protected $fillable = [
        'tenant_id',
        'employer_job_id',
        'created_by_id',
        'trigger',
        'round',
        'min_score',
        'action',
        'target_stage',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }

    /** @return HasMany<EmployerAutomationRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(EmployerAutomationRun::class);
    }
}
