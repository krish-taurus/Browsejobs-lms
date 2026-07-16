<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A marketing lead captured from the public site (masterclass, counselling,
 * bootcamp, contact, waitlist). The P2.1 CRM works it through the pipeline:
 * stage, counselor assignment, rule-based score, dedupe/merge, contact
 * timeline, tasks, and the speed-to-lead SLA.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $lead_type
 * @property string $name
 * @property string $phone
 * @property string|null $phone_normalized
 * @property string|null $email
 * @property string|null $course_slug
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property Carbon $consented_at
 * @property int|null $lead_stage_id
 * @property int|null $assigned_to
 * @property int $score
 * @property Carbon|null $first_touch_at
 * @property Carbon|null $sla_due_at
 * @property Carbon|null $sla_breached_at
 * @property int|null $merged_into_id
 */
class Lead extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPES = ['masterclass', 'counselling', 'bootcamp', 'contact', 'waitlist'];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lead_type', 'name', 'phone', 'phone_normalized', 'email', 'course_slug', 'message',
        'utm_source', 'utm_medium', 'utm_campaign', 'page', 'ip',
        'consented_at', 'consent_version', 'crm_synced',
        'lead_stage_id', 'assigned_to', 'score',
        'first_touch_at', 'sla_due_at', 'sla_breached_at', 'merged_into_id', 'last_replied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'crm_synced' => 'boolean',
            'score' => 'integer',
            'first_touch_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'last_replied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LeadStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'lead_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'merged_into_id');
    }

    /**
     * @return HasMany<ContactTimelineEvent, $this>
     */
    public function timelineEvents(): HasMany
    {
        return $this->hasMany(ContactTimelineEvent::class);
    }

    /**
     * @return HasMany<CrmTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }

    /** A merged-away lead is a tombstone pointing at its survivor. */
    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }
}
