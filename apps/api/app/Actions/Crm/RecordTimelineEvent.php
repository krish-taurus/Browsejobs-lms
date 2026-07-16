<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\User;

/**
 * Appends one event to a lead's contact timeline (PRD §6.12). Tenant is stamped
 * by BelongsToTenant from the active TenantContext.
 */
final class RecordTimelineEvent
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(Lead $lead, string $type, ?string $body = null, ?array $meta = null, ?User $actor = null): ContactTimelineEvent
    {
        return ContactTimelineEvent::query()->create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'type' => $type,
            'actor_id' => $actor?->getKey(),
            'body' => $body,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);
    }
}
