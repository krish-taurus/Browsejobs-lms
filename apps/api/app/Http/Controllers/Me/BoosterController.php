<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Boosters\BuildGithubPortfolio;
use App\Actions\Boosters\GeneratePrepPack;
use App\Actions\Boosters\OptimizeLinkedin;
use App\Http\Controllers\Controller;
use App\Models\CareerBooster;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Career+ job-probability boosters (PRD §6.20, P4.6b). `index` is open so the client can
 * show the Career+ upsell and any previously generated boosters; the generate endpoints
 * are gated by the `career-plus` middleware.
 */
final class BoosterController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $entitlements): JsonResponse {
            $boosters = CareerBooster::query()
                ->where('user_id', $request->user()->id)
                ->get()
                ->keyBy('kind')
                ->map(fn (CareerBooster $b) => ['content' => $b->content, 'source' => $b->content_source, 'updated_at' => $b->updated_at?->toIso8601String()]);

            return response()->json([
                'data' => [
                    'career_plus' => $entitlements->hasEntitlement($request->user(), 'career_plus'),
                    'linkedin' => $boosters->get(CareerBooster::KIND_LINKEDIN),
                    'github' => $boosters->get(CareerBooster::KIND_GITHUB),
                    'prep_pack' => $boosters->get(CareerBooster::KIND_PREP_PACK),
                ],
            ]);
        });
    }

    public function linkedin(Request $request, OptimizeLinkedin $action): JsonResponse
    {
        return $this->generate($request, fn () => $action->handle($request->user()));
    }

    public function github(Request $request, BuildGithubPortfolio $action): JsonResponse
    {
        return $this->generate($request, fn () => $action->handle($request->user()));
    }

    public function prepPack(Request $request, GeneratePrepPack $action): JsonResponse
    {
        return $this->generate($request, fn () => $action->handle($request->user()));
    }

    /**
     * The boosters swallow a budget/transport failure into a deterministic fallback (the
     * subscription always yields output, never an error), so this just persists and returns.
     *
     * @param  callable(): CareerBooster  $run
     */
    private function generate(Request $request, callable $run): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($run): JsonResponse {
            $booster = $run();

            return response()->json(['data' => ['content' => $booster->content, 'source' => $booster->content_source]]);
        });
    }
}
