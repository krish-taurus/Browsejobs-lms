<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SalaryBenchmark;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public salary explorer data (PRD §6.21 benchmarks, homepage widget +
 * salary SEO pages). Whole curated dataset in one cached payload — small,
 * and the client filters instantly. Money stays server-owned, in paise.
 */
final class SalaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rows = Cache::remember('salaries:public', 3600, fn () => SalaryBenchmark::query()
            ->withoutGlobalScope(TenantScope::class)
            ->orderBy('role_title')->orderBy('city')->orderBy('experience_band')
            ->get()
            ->map(fn (SalaryBenchmark $b): array => [
                'role' => $b->role_title,
                'city' => $b->city,
                'band' => $b->experience_band,
                'p25_paise' => $b->p25_ctc_paise,
                'p50_paise' => $b->p50_ctc_paise,
                'p75_paise' => $b->p75_ctc_paise,
            ])
            ->all());

        return response()->json(['data' => ['rows' => $rows]]);
    }
}
