<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Events\LeadStageChanged;
use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Moves a lead to a pipeline stage (PRD §6.12 kanban). Records the change on the
 * contact timeline and audits it — a manual stage move is an override worth an
 * audit entry per the Definition of Done.
 */
final readonly class MoveLeadStage
{
    public function __construct(
        private RecordTimelineEvent $timeline,
        private AuditLogger $audit,
    ) {}

    public function handle(Lead $lead, LeadStage $stage, ?User $actor = null): Lead
    {
        if ($stage->tenant_id !== $lead->tenant_id) {
            throw ValidationException::withMessages([
                'stage' => 'Stage and lead belong to different tenants.',
            ]);
        }

        $from = $lead->stage;

        if ($from !== null && $from->id === $stage->id) {
            return $lead;
        }

        $lead->lead_stage_id = $stage->id;
        $lead->save();

        $this->timeline->handle(
            $lead,
            ContactTimelineEvent::TYPE_STAGE_CHANGED,
            "Moved to {$stage->name}",
            ['from' => $from?->slug, 'to' => $stage->slug],
            $actor,
        );

        $this->audit->log(
            action: 'crm.stage_changed',
            target: $lead,
            metadata: ['from' => $from?->slug, 'to' => $stage->slug],
            actor: $actor,
        );

        LeadStageChanged::dispatch($lead, $from, $stage);

        return $lead;
    }
}
