<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Crm\RecordTimelineEvent;
use App\Models\ContactTimelineEvent;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Speed-to-lead SLA enforcer (PRD §6.12). Dispatched with a delay to the lead's
 * `sla_due_at` on capture. When it fires, if the lead is still un-touched and
 * past due it flags a breach, drops an SLA task on the assignee, and records a
 * timeline event. Idempotent: re-running after a breach or a first touch is a
 * no-op, and it self-guards against the sync driver ignoring the delay.
 */
final class CheckLeadSla implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $leadId) {}

    public function handle(RecordTimelineEvent $timeline): void
    {
        $lead = Lead::query()->withoutGlobalScopes()->find($this->leadId);

        if ($lead === null
            || $lead->sla_due_at === null
            || $lead->first_touch_at !== null
            || $lead->sla_breached_at !== null
            || $lead->sla_due_at->isFuture()) {
            return;
        }

        $tenant = Tenant::query()->find($lead->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($lead, $timeline): void {
            $lead->sla_breached_at = now();
            $lead->save();

            if ($lead->assigned_to !== null) {
                CrmTask::query()->create([
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id,
                    'assigned_to' => $lead->assigned_to,
                    'title' => "Call {$lead->name} — speed-to-lead SLA breached",
                    'due_at' => now(),
                    'source' => CrmTask::SOURCE_SLA,
                ]);
            }

            $timeline->handle(
                $lead,
                ContactTimelineEvent::TYPE_SLA_BREACHED,
                'Speed-to-lead SLA breached before first touch.',
                ['sla_due_at' => $lead->sla_due_at?->toIso8601String()],
            );
        });
    }
}
