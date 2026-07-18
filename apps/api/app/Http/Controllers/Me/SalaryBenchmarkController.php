<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\SalaryBenchmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student-facing salary benchmarks (PRD §6.21) for offer-evaluation and
 * negotiation guidance. Reference data, not per-student — optionally filtered by
 * role/city. CTC returned in LPA for display.
 */
final class SalaryBenchmarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $role = $request->string('role')->trim()->value();
        $city = $request->string('city')->trim()->value();

        // The me/ route group doesn't set tenant context, so scope explicitly to
        // the caller's tenant (benchmarks are shared within a tenant, not per-user).
        $rows = SalaryBenchmark::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($role !== '', fn ($q) => $q->where('role_title', 'like', "%{$role}%"))
            ->when($city !== '', fn ($q) => $q->where('city', 'like', "%{$city}%"))
            ->orderBy('role_title')
            ->orderBy('city')
            ->limit(50)
            ->get()
            ->map(fn (SalaryBenchmark $b) => [
                'role_title' => $b->role_title,
                'city' => $b->city,
                'experience_band' => $b->experience_band,
                'p25_lpa' => round($b->p25_ctc_paise / (100_000 * 100), 2),
                'p50_lpa' => round($b->p50_ctc_paise / (100_000 * 100), 2),
                'p75_lpa' => round($b->p75_ctc_paise / (100_000 * 100), 2),
                'source' => $b->source,
            ]);

        return response()->json(['data' => $rows]);
    }
}
