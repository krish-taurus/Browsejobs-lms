<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadStage;
use Illuminate\Http\JsonResponse;

/**
 * Funnel dashboard (PRD §6.10): ad→masterclass→bootcamp→paid conversion, and a
 * per-UTM-campaign breakdown. The CRM pipeline *is* the funnel — counts derive
 * from each lead's stage position. Gated by `can:manage-leads`.
 */
final class FunnelController extends Controller
{
    public function index(): JsonResponse
    {
        $stages = LeadStage::query()->orderBy('position')->get();
        $posOf = fn (string $slug): int => (int) ($stages->firstWhere('slug', $slug)?->position ?? 9999);
        $stagePos = $stages->pluck('position', 'id');

        $leads = Lead::query()
            ->whereNull('merged_into_id')
            ->get(['id', 'lead_stage_id', 'utm_campaign']);

        $atOrPast = fn (int $pos): int => $leads
            ->filter(fn (Lead $l) => (int) ($stagePos[$l->lead_stage_id] ?? 0) >= $pos)
            ->count();

        $paidPos = $posOf('enrolled');

        $funnel = [
            ['key' => 'lead', 'label' => 'Leads', 'count' => $leads->count()],
            ['key' => 'masterclass', 'label' => 'Masterclass', 'count' => $atOrPast($posOf('masterclass-registered'))],
            ['key' => 'bootcamp', 'label' => 'Bootcamp', 'count' => $atOrPast($posOf('bootcamp'))],
            ['key' => 'reserved', 'label' => 'Reserved', 'count' => $atOrPast($posOf('reserved'))],
            ['key' => 'paid', 'label' => 'Paid', 'count' => $atOrPast($paidPos)],
        ];

        $campaigns = $leads
            ->filter(fn (Lead $l) => $l->utm_campaign !== null && $l->utm_campaign !== '')
            ->groupBy('utm_campaign')
            ->map(fn ($group, $campaign) => [
                'campaign' => $campaign,
                'leads' => $group->count(),
                'paid' => $group->filter(fn (Lead $l) => (int) ($stagePos[$l->lead_stage_id] ?? 0) >= $paidPos)->count(),
            ])
            ->values()
            ->sortByDesc('leads')
            ->take(20)
            ->values();

        return response()->json(['data' => [
            'total_leads' => $leads->count(),
            'funnel' => $funnel,
            'campaigns' => $campaigns,
        ]]);
    }
}
