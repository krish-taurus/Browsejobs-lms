<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Events\LeadCaptured;
use App\Jobs\CheckLeadSla;
use App\Models\ContactTimelineEvent;
use App\Models\CrmAssignmentRule;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Support\Crm\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;

/**
 * Single entry point for every lead capture source (site forms, CSV, manual,
 * Meta Lead Ads webhook — PRD §6.12). Normalizes the phone for dedupe, drops the
 * lead on the first pipeline stage, auto-assigns a counselor, scores it, arms
 * the speed-to-lead SLA, and records the opening timeline event.
 */
final readonly class CaptureLead
{
    public function __construct(
        private AssignLead $assign,
        private ScoreLead $score,
        private RecordTimelineEvent $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  lead_type, name, phone, email,
     *                                            course_slug, message, utm_*, page, ip
     */
    public function handle(Tenant $tenant, array $attributes): Lead
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant, $attributes): Lead {
            $rule = CrmAssignmentRule::query()->where('tenant_id', $tenant->id)->first();
            $slaMinutes = $rule?->sla_minutes ?? 15;

            $firstStage = LeadStage::query()
                ->where('tenant_id', $tenant->id)
                ->orderBy('position')
                ->first();

            $lead = Lead::query()->create([
                ...$attributes,
                'tenant_id' => $tenant->id,
                'phone_normalized' => PhoneNormalizer::normalize((string) ($attributes['phone'] ?? '')),
                'lead_stage_id' => $firstStage?->id,
                'consented_at' => $attributes['consented_at'] ?? now(),
                'consent_version' => $attributes['consent_version'] ?? 'v1',
                'sla_due_at' => now()->addMinutes($slaMinutes),
            ]);

            $this->score->handle($lead);
            $this->assign->handle($lead);

            $this->timeline->handle(
                $lead,
                ContactTimelineEvent::TYPE_CREATED,
                'Lead captured from '.($attributes['page'] ?? $attributes['lead_type'] ?? 'unknown source'),
                ['source' => $attributes['utm_source'] ?? null],
            );

            LeadCaptured::dispatch($lead);
            CheckLeadSla::dispatch($lead->id)->delay($lead->sla_due_at);

            return $lead->refresh();
        });
    }
}
