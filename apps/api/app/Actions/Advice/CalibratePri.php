<?php

declare(strict_types=1);

namespace App\Actions\Advice;

use App\Models\PriCalibration;
use App\Models\Tenant;
use App\Support\Advice\OutcomeCohort;
use App\Support\Tenancy\TenantContext;

/**
 * Calibrates PRI weights from placement-outcome correlations (PRD §6.21). For a
 * tenant's outcome cohort, it measures how strongly each PRI signal (mastery,
 * engagement, attendance) correlates with actually getting placed, then sets each
 * weight proportional to its positive correlation. Evidence over opinion.
 *
 * Guardrails: below MIN_COHORT students, or with too few in either the placed or
 * not-placed group, or when no signal correlates positively, it does NOT calibrate
 * — the ScoreCalculator keeps the config defaults. Uses anonymised aggregates
 * only; no individual is identifiable in the stored result.
 */
final readonly class CalibratePri
{
    /** Minimum cohort and per-group sizes before we trust a correlation. */
    public const MIN_COHORT = 8;

    public const MIN_PER_GROUP = 3;

    private const SIGNALS = ['mastery', 'engagement', 'attendance'];

    public function __construct(private OutcomeCohort $cohort) {}

    public function handle(Tenant $tenant): ?PriCalibration
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant): ?PriCalibration {
            $rows = $this->cohort->build();

            $placed = array_filter($rows, fn ($r) => $r['placed']);
            $notPlaced = array_filter($rows, fn ($r) => ! $r['placed']);

            if (count($rows) < self::MIN_COHORT
                || count($placed) < self::MIN_PER_GROUP
                || count($notPlaced) < self::MIN_PER_GROUP) {
                return null; // not enough evidence — keep config defaults
            }

            $correlations = [];
            foreach (self::SIGNALS as $signal) {
                $correlations[$signal] = $this->pointBiserial($rows, $signal);
            }

            $weights = $this->weightsFrom($correlations);
            if ($weights === null) {
                return null; // no positive signal — don't calibrate
            }

            return PriCalibration::query()->create([
                'tenant_id' => $tenant->id,
                'weights' => $weights,
                'correlations' => array_map(fn ($c) => round($c, 4), $correlations),
                'cohort_size' => count($rows),
                'applied' => true,
                'computed_at' => now(),
            ]);
        });
    }

    /**
     * Point-biserial correlation of one signal against the binary placed outcome.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function pointBiserial(array $rows, string $signal): float
    {
        $values = array_map(fn ($r) => (float) $r[$signal], $rows);
        $n = count($values);
        $mean = array_sum($values) / $n;

        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }
        $sd = sqrt($variance / $n);
        if ($sd == 0.0) {
            return 0.0;
        }

        $placedVals = [];
        $notPlacedVals = [];
        foreach ($rows as $r) {
            $r['placed'] ? $placedVals[] = (float) $r[$signal] : $notPlacedVals[] = (float) $r[$signal];
        }

        $m1 = array_sum($placedVals) / count($placedVals);
        $m0 = array_sum($notPlacedVals) / count($notPlacedVals);
        $p = count($placedVals) / $n;
        $q = 1 - $p;

        return (($m1 - $m0) / $sd) * sqrt($p * $q);
    }

    /**
     * Weights ∝ positive correlation, renormalised to sum 1. Null when no signal
     * has a positive correlation (nothing to learn from).
     *
     * @param  array<string, float>  $correlations
     * @return array{mastery: float, engagement: float, attendance: float}|null
     */
    private function weightsFrom(array $correlations): ?array
    {
        $positive = array_map(fn ($c) => max(0.0, $c), $correlations);
        $total = array_sum($positive);
        if ($total <= 0.0) {
            return null;
        }

        return [
            'mastery' => round($positive['mastery'] / $total, 4),
            'engagement' => round($positive['engagement'] / $total, 4),
            'attendance' => round($positive['attendance'] / $total, 4),
        ];
    }
}
