<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\DeflectionOutcome;
use App\Models\Ticket;
use App\Models\TicketDeflection;
use Illuminate\Support\Carbon;

/**
 * Aggregates a week of support activity for the admin themes report (PRD §6.13: "a
 * weekly 'top concern themes' report to admin — fix root causes, not just tickets").
 *
 * Deliberately counts rather than asks a model to count: volume by category, sentiment,
 * repeat offenders (duplicates), CSAT, and SLA breaches are arithmetic, and arithmetic
 * an LLM re-derives is arithmetic that can be wrong. The model's job is to say what the
 * numbers *mean*; the numbers themselves come from here.
 *
 * Includes the deflection accept rate from P3.5d-a, which is the honest read on the
 * support corpus: a category with heavy ticket volume and a low accept rate is a
 * category whose policy documents do not exist or do not answer the real question.
 *
 * Runs inside the caller's tenant context.
 */
final readonly class BuildSupportThemes
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Carbon $from, Carbon $to): array
    {
        $tickets = Ticket::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'category', 'ai_sentiment', 'ai_duplicate_of_id', 'csat_rating', 'breached_at', 'resolution_breached_at', 'subject']);

        $byCategory = $tickets
            ->groupBy(fn (Ticket $t) => $t->category->value)
            ->map->count()
            ->sortDesc();

        $deflections = TicketDeflection::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['category', 'outcome']);

        $offered = $deflections->count();
        $accepted = $deflections->where('outcome', DeflectionOutcome::Accepted)->count();

        $csatRated = $tickets->whereNotNull('csat_rating');

        return [
            'total' => $tickets->count(),
            'by_category' => $byCategory->all(),
            'distressed' => $tickets->filter(fn (Ticket $t) => $t->ai_sentiment?->isDistressed() === true)->count(),
            'duplicates' => $tickets->whereNotNull('ai_duplicate_of_id')->count(),
            'breached' => $tickets->filter(fn (Ticket $t) => $t->breached_at !== null || $t->resolution_breached_at !== null)->count(),
            'csat_avg' => $csatRated->isEmpty() ? null : round($csatRated->avg('csat_rating'), 1),
            'deflection' => [
                'offered' => $offered,
                'accepted' => $accepted,
                // Null rather than 0% when nothing was offered — "we never tried" and
                // "we tried and it never landed" are different problems.
                'accept_pct' => $offered === 0 ? null : (int) round($accepted / $offered * 100),
                'by_category' => $deflections
                    ->groupBy('category')
                    ->map(fn ($rows) => [
                        'offered' => $rows->count(),
                        'accepted' => $rows->where('outcome', DeflectionOutcome::Accepted)->count(),
                    ])
                    ->all(),
            ],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ];
    }
}
