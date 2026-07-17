<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePointsSettingsRequest;
use App\Support\Points\PointsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Admin-configurable leaderboard weights (PRD §6.16). Mirrors the monetization
 * settings singleton pattern; gated by can:manage-monetization (ADR 0027).
 */
final class PointsSettingController extends Controller
{
    private const FIELDS = [
        'attendance_points', 'punctuality_points', 'quiz_points', 'lab_points',
        'streak_day_points', 'mock_points', 'daily_cap', 'attendance_min_pct',
    ];

    public function __construct(private readonly PointsService $points) {}

    public function show(): JsonResponse
    {
        $settings = $this->points->settingsFor(app(TenantContext::class)->id());

        return response()->json(['data' => $settings->only(self::FIELDS)]);
    }

    public function update(UpdatePointsSettingsRequest $request): JsonResponse
    {
        $settings = $this->points->settingsFor(app(TenantContext::class)->id());
        $settings->fill($request->validated())->save();

        return response()->json(['data' => $settings->only(self::FIELDS)]);
    }
}
