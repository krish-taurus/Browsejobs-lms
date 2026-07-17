<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Reports\BuildCounselorDigest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Counselor risk dashboard (PRD §6.4/§6.10): at-risk students sorted by dropout
 * risk, with day-over-day movers, red flags, and a rule-based intervention script.
 * Gated by `can:manage-leads`, tenant-scoped. Shares BuildCounselorDigest with the
 * daily digest so the payload is computed in one place.
 */
final class RiskController extends Controller
{
    public function __construct(private readonly BuildCounselorDigest $digest) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->digest->handle()]);
    }
}
