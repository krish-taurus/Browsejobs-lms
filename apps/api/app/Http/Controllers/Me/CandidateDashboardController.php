<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Support\Candidates\CandidateDashboard;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One round trip for the candidate command centre (PRD-E F11).
 */
final class CandidateDashboardController extends Controller
{
    public function show(Request $request, EntitlementService $entitlements): JsonResponse
    {
        $user = $request->user();

        $data = app(TenantContext::class)->run(
            $user->tenant,
            fn (): array => (new CandidateDashboard($user, $entitlements))->build(),
        );

        return response()->json(['data' => $data]);
    }
}
