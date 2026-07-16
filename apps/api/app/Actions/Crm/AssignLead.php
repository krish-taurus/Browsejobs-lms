<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Events\LeadAssigned;
use App\Models\ContactTimelineEvent;
use App\Models\CrmAssignmentRule;
use App\Models\Lead;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Assigns a lead to a counselor (PRD §6.12 "assignment rules round-robin / by
 * course"). Auto mode applies the tenant's CrmAssignmentRule on capture; passing
 * an explicit counselor performs an audited manual reassignment.
 */
final readonly class AssignLead
{
    public function __construct(
        private RecordTimelineEvent $timeline,
        private AuditLogger $audit,
    ) {}

    public function handle(Lead $lead, ?User $counselor = null, ?User $actor = null): Lead
    {
        $manual = $counselor !== null;
        $counselor ??= $this->resolveAuto($lead);

        if ($counselor === null) {
            return $lead; // No eligible counselor; lead stays in the unassigned queue.
        }

        $lead->assigned_to = $counselor->id;
        $lead->save();

        $this->timeline->handle(
            $lead,
            ContactTimelineEvent::TYPE_ASSIGNED,
            "Assigned to {$counselor->name}".($manual ? '' : ' (auto)'),
            ['counselor_id' => $counselor->id, 'manual' => $manual],
            $manual ? $actor : null,
        );

        if ($manual) {
            $this->audit->log(
                action: 'crm.lead_reassigned',
                target: $lead,
                metadata: ['counselor_id' => $counselor->id],
                actor: $actor,
            );
        }

        LeadAssigned::dispatch($lead, $counselor, $manual);

        return $lead;
    }

    private function resolveAuto(Lead $lead): ?User
    {
        $rule = CrmAssignmentRule::query()->where('tenant_id', $lead->tenant_id)->first();
        $pool = $this->pool($lead);

        if ($rule?->mode === CrmAssignmentRule::MODE_BY_COURSE && $lead->course_slug !== null) {
            $mapped = $rule->by_course_map[$lead->course_slug] ?? null;
            $match = $mapped !== null ? $pool->firstWhere('id', (int) $mapped) : null;
            if ($match !== null) {
                return $match;
            }
        }

        return $this->nextRoundRobin($pool, $rule);
    }

    /**
     * @param  Collection<int, User>  $pool
     */
    private function nextRoundRobin(Collection $pool, ?CrmAssignmentRule $rule): ?User
    {
        if ($pool->isEmpty()) {
            return null;
        }

        $ids = $pool->pluck('id')->all();
        $pointer = $rule?->round_robin_pointer;
        $currentIndex = $pointer !== null ? array_search($pointer, $ids, true) : false;
        $nextIndex = $currentIndex === false ? 0 : ($currentIndex + 1) % count($ids);
        $next = $pool->get($nextIndex);

        if ($rule !== null && $next !== null) {
            $rule->round_robin_pointer = $next->id;
            $rule->save();
        }

        return $next;
    }

    /**
     * The counselor pool: every staff user in the tenant whose roles grant
     * `manage-leads` (counselor + admin), ordered stably for round-robin.
     *
     * @return Collection<int, User>
     */
    private function pool(Lead $lead): Collection
    {
        return User::query()
            ->where('tenant_id', $lead->tenant_id)
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'manage-leads'))
            ->orderBy('id')
            ->get();
    }
}
