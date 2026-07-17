<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student's weekly reports (PRD §6.10). Under auth:sanctum without tenant.user, so
 * work wraps in the student's tenant context. Only the student's own weekly reports are
 * visible (briefs/digests are staff pushes).
 */
final class MyReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $reports = Report::query()
                ->where('recipient_id', $request->user()->id)
                ->where('type', ReportType::WeeklyStudent->value)
                ->orderByDesc('period_start')
                ->get();

            return ReportResource::collection($reports)->response();
        });
    }

    public function show(Request $request, int $report): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $report): JsonResponse {
            $model = Report::query()
                ->where('recipient_id', $request->user()->id)
                ->where('type', ReportType::WeeklyStudent->value)
                ->findOrFail($report);

            $model->markRead();

            return (new ReportResource($model))->response();
        });
    }
}
