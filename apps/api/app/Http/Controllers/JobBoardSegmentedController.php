<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\JobBoard\JobBoardQuery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The segmented job board (PRD-E F10) — served both to signed-out visitors
 * (tenant resolved by domain) and to signed-in candidates, who additionally
 * get their own application state marked on internal postings.
 */
final class JobBoardSegmentedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $term = $request->filled('q') ? $request->string('q')->trim()->toString() : null;

        $run = fn (): array => (new JobBoardQuery($viewer))->handle($term);

        $board = $viewer !== null
            ? app(TenantContext::class)->run($viewer->tenant, $run)
            : $run();

        return response()->json(['data' => $board]);
    }
}
