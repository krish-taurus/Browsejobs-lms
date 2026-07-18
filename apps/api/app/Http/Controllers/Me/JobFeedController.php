<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\JobFeedItem;
use App\Models\JobFeedSave;
use App\Support\JobFeed\JobsForYou;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student "Jobs for You" feed (PRD §6.22): relevance-ranked openings with a
 * match-% badge and the "why/gap" explanation, plus save/dismiss. Reads only the
 * viewer's own tenant + interactions.
 */
final class JobFeedController extends Controller
{
    public function index(Request $request, JobsForYou $feed): JsonResponse
    {
        $rows = $feed->for($request->user());

        return response()->json([
            'data' => array_map(fn (array $row) => [
                'id' => $row['item']->id,
                'title' => $row['item']->title,
                'company' => $row['item']->company,
                'location' => $row['item']->location,
                'work_mode' => $row['item']->work_mode,
                'source_kind' => $row['item']->source_kind,
                'apply_url' => $row['item']->apply_url,
                'posted_at' => $row['item']->posted_at?->toDateString(),
                'match_pct' => $row['match_pct'],
                'matched' => $row['matched'],
                'gap' => $row['gap'],
                'saved' => $row['saved'],
            ], $rows),
        ]);
    }

    public function save(Request $request, JobFeedItem $item): JsonResponse
    {
        return $this->setState($request, $item, JobFeedSave::STATE_SAVED);
    }

    public function dismiss(Request $request, JobFeedItem $item): JsonResponse
    {
        return $this->setState($request, $item, JobFeedSave::STATE_DISMISSED);
    }

    private function setState(Request $request, JobFeedItem $item, string $state): JsonResponse
    {
        $user = $request->user();
        abort_unless($item->tenant_id === $user->tenant_id, 404);

        JobFeedSave::query()->updateOrCreate(
            ['user_id' => $user->id, 'job_feed_item_id' => $item->id],
            ['tenant_id' => $user->tenant_id, 'state' => $state],
        );

        return response()->json(['data' => ['state' => $state]]);
    }
}
