<?php

declare(strict_types=1);

namespace App\Http\Controllers\Placement;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;

/**
 * The Proof Engine (PRD §6.11): anonymised placement aggregates for the
 * public site. Real records only, the mandatory disclaimer travels with
 * every response, and nothing student-identifying ever leaves (DPDP).
 */
final class ProofController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $placed = JobApplication::query()->where('status', JobApplication::STATUS_PLACED);

        $companies = (clone $placed)->with('posting:id,company')->get()
            ->pluck('posting.company')->filter()->unique()->count();

        return response()->json(['data' => [
            'placed_count' => (clone $placed)->count(),
            'offers_count' => JobApplication::query()
                ->whereIn('status', [JobApplication::STATUS_OFFER, JobApplication::STATUS_PLACED])
                ->count(),
            'hiring_companies' => $companies,
            'last_90_days' => (clone $placed)->where('placed_at', '>=', now()->subDays(90))->count(),
            'disclaimer' => (string) config('placement.disclaimer'),
        ]]);
    }
}
