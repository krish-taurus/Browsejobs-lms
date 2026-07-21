<?php

declare(strict_types=1);

namespace App\Actions\Conversion;

use App\Actions\Crm\MoveLeadStage;
use App\Enums\MessageChannel;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tenant;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;

/**
 * Auto-invites masterclass leads to the free 7-day bootcamp (PRD §5 funnel spine)
 * — the "prompted for a free bootcamp" step, fully automated. For each lead sitting
 * at the `masterclass-registered` stage it sends the bootcamp invite on WhatsApp +
 * email (leads stay leads; no account until they pay) and advances them to the
 * `bootcamp` stage — which is the dedup, so a lead is invited exactly once. Run
 * daily by `bootcamp:invite`.
 */
final readonly class RunBootcampInvites
{
    public function __construct(
        private Messenger $messenger,
        private MoveLeadStage $moveStage,
    ) {}

    /** @return int number of leads invited */
    public function handle(): int
    {
        $invited = 0;

        // One masterclass-registered stage row exists per tenant.
        LeadStage::query()->withoutGlobalScopes()->where('slug', 'masterclass-registered')->get()
            ->each(function (LeadStage $fromStage) use (&$invited): void {
                $tenant = Tenant::query()->find($fromStage->tenant_id);
                if ($tenant === null) {
                    return;
                }

                app(TenantContext::class)->run($tenant, function () use ($fromStage, &$invited): void {
                    $toStage = LeadStage::query()->where('slug', 'bootcamp')->first();
                    if ($toStage === null) {
                        return;
                    }

                    $link = rtrim((string) config('app.frontend_url', ''), '/').'/bootcamp';

                    Lead::query()
                        ->where('lead_stage_id', $fromStage->id)
                        ->whereNull('merged_into_id')
                        ->get()
                        ->each(function (Lead $lead) use ($toStage, $link, &$invited): void {
                            $vars = ['name' => $lead->name, 'link' => $link];

                            if ($lead->phone !== null && $lead->phone !== '') {
                                $this->messenger->sendRaw($lead->tenant_id, MessageChannel::WhatsApp, $lead->phone, 'bootcamp_invite', $vars, $lead);
                            }
                            if ($lead->email !== null && $lead->email !== '') {
                                $this->messenger->sendRaw($lead->tenant_id, MessageChannel::Email, $lead->email, 'bootcamp_invite', $vars, $lead);
                            }

                            // Advancing the stage is the dedup: next run won't re-pick this lead.
                            $this->moveStage->handle($lead, $toStage);
                            $invited++;
                        });
                });
            });

        return $invited;
    }
}
