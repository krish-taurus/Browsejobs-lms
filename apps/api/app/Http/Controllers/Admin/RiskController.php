<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RiskBand;
use App\Http\Controllers\Controller;
use App\Models\ScoreSnapshot;
use App\Models\StudentScore;
use App\Support\Scoring\InterventionScript;
use Illuminate\Http\JsonResponse;

/**
 * Counselor risk dashboard (PRD §6.4/§6.10): at-risk students sorted by dropout
 * risk, with day-over-day movers, red flags derived from the mastery/next-action,
 * and a rule-based intervention script. Gated by `can:manage-leads`, tenant-scoped.
 */
final class RiskController extends Controller
{
    public function __construct(private readonly InterventionScript $scripts) {}

    public function index(): JsonResponse
    {
        $scores = StudentScore::query()
            ->with('student:id,name,phone')
            ->orderByDesc('risk_dropout')
            ->limit(100)
            ->get();

        // Previous snapshot per student (yesterday-or-earlier) for the mover delta.
        $prev = ScoreSnapshot::query()
            ->whereIn('user_id', $scores->pluck('user_id'))
            ->where('captured_on', '<', now()->startOfDay())
            ->orderByDesc('captured_on')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($group) => $group->first()?->risk_dropout);

        $rows = $scores->map(function (StudentScore $s) use ($prev): array {
            $redFlags = $s->red_flags ?? [];

            return [
                'user_id' => $s->user_id,
                'name' => $s->student?->name,
                'phone' => $s->student?->phone,
                'risk_dropout' => $s->risk_dropout,
                'risk_band' => RiskBand::fromScore($s->risk_dropout)->value,
                'engagement' => $s->engagement,
                'pri' => $s->pri,
                'mover' => $s->risk_dropout - (int) ($prev[$s->user_id] ?? $s->risk_dropout),
                'red_flags' => $redFlags,
                'script' => $this->scripts->for($redFlags),
            ];
        });

        return response()->json(['data' => [
            'high_risk' => $rows->where('risk_band', RiskBand::High->value)->count(),
            'movers' => $rows->filter(fn ($r) => $r['mover'] > 0)->sortByDesc('mover')->take(10)->values()->all(),
            'students' => $rows->values()->all(),
        ]]);
    }
}
