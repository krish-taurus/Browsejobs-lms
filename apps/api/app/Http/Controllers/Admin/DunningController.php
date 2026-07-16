<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessBlock;
use App\Models\FeePlan;
use App\Support\Fees\DunningStatus;
use Illuminate\Http\JsonResponse;

/**
 * Admin dunning dashboard (PRD §6.8): aging, expected collections, failed
 * instalments, and the overdue-student worklist. Gated by `can:manage-fees`.
 */
final class DunningController extends Controller
{
    public function index(DunningStatus $dunning): JsonResponse
    {
        $plans = FeePlan::query()
            ->where('status', FeePlanStatus::Active->value)
            ->with(['instalments', 'student:id,name', 'batch:id,number'])
            ->get();

        $aging = ['bucket_0_7' => 0, 'bucket_8_30' => 0, 'bucket_30_plus' => 0];
        $expectedCollections = 0;
        $overdue = [];

        foreach ($plans as $plan) {
            $snapshot = $dunning->for($plan);
            $expectedCollections += $snapshot->outstandingPaise;

            if (! $snapshot->overdue) {
                continue;
            }

            $overdueDays = -$snapshot->daysToDue;
            $bucket = $overdueDays <= 7 ? 'bucket_0_7' : ($overdueDays <= 30 ? 'bucket_8_30' : 'bucket_30_plus');
            $aging[$bucket]++;

            $overdue[] = [
                'fee_plan_id' => $plan->id,
                'student' => $plan->student?->name,
                'batch' => $plan->batch?->number,
                'overdue_days' => $overdueDays,
                'outstanding_paise' => $snapshot->outstandingPaise,
                'block' => $snapshot->blockLevel,
            ];
        }

        usort($overdue, fn ($a, $b) => $b['overdue_days'] <=> $a['overdue_days']);

        $failedCount = FeePlan::query()
            ->where('status', FeePlanStatus::Active->value)
            ->whereHas('instalments', fn ($q) => $q->where('status', InstalmentStatus::Failed->value))
            ->count();

        return response()->json(['data' => [
            'expected_collections_paise' => $expectedCollections,
            'overdue_count' => count($overdue),
            'failed_count' => $failedCount,
            'active_blocks_count' => AccessBlock::query()->whereNull('lifted_at')->count(),
            'aging' => $aging,
            'overdue' => $overdue,
        ]]);
    }
}
