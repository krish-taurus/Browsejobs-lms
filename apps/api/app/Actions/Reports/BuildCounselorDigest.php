<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\RiskBand;
use App\Models\ScoreSnapshot;
use App\Models\StudentScore;
use App\Support\Scoring\InterventionScript;

/**
 * Assembles the counselor risk digest (PRD §6.4/§6.10): at-risk students sorted by
 * dropout risk, day-over-day movers, red flags, and a rule-based intervention script.
 * The single source shared by the RiskController dashboard and the daily digest.
 * Runs inside the caller's tenant context.
 */
final readonly class BuildCounselorDigest
{
    public function __construct(private InterventionScript $scripts) {}

    /**
     * @return array{high_risk: int, movers: list<array<string, mixed>>, students: list<array<string, mixed>>}
     */
    public function handle(): array
    {
        $scores = StudentScore::query()
            ->with('student:id,name,phone')
            ->orderByDesc('risk_dropout')
            ->limit(100)
            ->get();

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

        return [
            'high_risk' => $rows->where('risk_band', RiskBand::High->value)->count(),
            'movers' => $rows->filter(fn ($r) => $r['mover'] > 0)->sortByDesc('mover')->take(10)->values()->all(),
            'students' => $rows->values()->all(),
        ];
    }
}
