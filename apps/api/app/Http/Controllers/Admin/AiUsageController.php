<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiEvent;
use Illuminate\Http\JsonResponse;

/**
 * AI usage + cost dashboard (CLAUDE.md ai_events). Read-only oversight of the
 * gateway's spend. Gated by `can:manage-monetization`; tenant-scoped. Cost is in
 * micro-rupees (₹1 = 1e6).
 */
final class AiUsageController extends Controller
{
    public function index(): JsonResponse
    {
        $today = AiEvent::query()->where('created_at', '>=', now()->startOfDay());

        $byPurpose = AiEvent::query()->get(['purpose', 'total_tokens', 'cost_micros'])
            ->groupBy(fn (AiEvent $e) => $e->purpose->value)
            ->map(fn ($group, $purpose) => [
                'purpose' => $purpose,
                'calls' => $group->count(),
                'tokens' => (int) $group->sum('total_tokens'),
                'cost_micros' => (int) $group->sum('cost_micros'),
            ])->values()->all();

        $recent = AiEvent::query()->orderByDesc('id')->limit(20)->get();

        return response()->json(['data' => [
            'today' => [
                'calls' => (clone $today)->count(),
                'tokens' => (int) (clone $today)->sum('total_tokens'),
                'cost_micros' => (int) (clone $today)->sum('cost_micros'),
                'budget_per_student' => (int) config('ai.daily_token_budget'),
            ],
            'by_purpose' => $byPurpose,
            'recent' => $recent->map(fn (AiEvent $e) => [
                'purpose' => $e->purpose->value,
                'model' => $e->model,
                'total_tokens' => $e->total_tokens,
                'cost_micros' => $e->cost_micros,
                'latency_ms' => $e->latency_ms,
                'status' => $e->status->value,
                'created_at' => $e->created_at?->toIso8601String(),
            ])->all(),
        ]]);
    }
}
