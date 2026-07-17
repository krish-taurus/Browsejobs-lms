<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Engagement\GenerateGapGuidance;
use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Models\Celebration;
use App\Models\CelebrationGuidance;
use App\Models\ContentHubItem;
use App\Models\PulseDigest;
use App\Models\PulseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student Pulse page (PRD §6.18 + §6.19): celebration wall, Market Pulse
 * digest with sources, and the Content Hub feed. Guidance cards are generated
 * on demand (throttle:ai) and cached per student.
 */
final class PulsePageController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $celebrations = Celebration::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->with('student:id,name')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $guidances = CelebrationGuidance::query()
            ->where('user_id', $user->id)
            ->whereIn('celebration_id', $celebrations->pluck('id'))
            ->get()
            ->keyBy('celebration_id');

        $digest = PulseDigest::query()->orderByDesc('digest_date')->first();
        $digestItems = $digest !== null
            ? PulseItem::query()->whereIn('id', $digest->item_ids)->get(['id', 'title', 'url', 'source_name'])
            : collect();

        return response()->json(['data' => [
            'celebrations' => $celebrations->map(fn (Celebration $c) => [
                'id' => $c->id,
                'display' => $c->displayName(),
                'role_title' => $c->role_title,
                'company' => $c->company,
                'published_at' => $c->published_at->toDateString(),
                'is_me' => $c->student_id === $user->id,
                'guidance' => $guidances->get($c->id)?->only(['intro', 'actions']),
            ]),
            'digest' => $digest === null ? null : [
                'date' => $digest->digest_date->toDateString(),
                'narrative' => $digest->narrative,
                'sources' => $digestItems->values(),
            ],
            'content' => ContentHubItem::query()
                ->where('is_active', true)
                ->orderByDesc('published_at')
                ->limit(20)
                ->get(['id', 'kind', 'title', 'url', 'published_at']),
        ]]);
    }

    public function guidance(Request $request, Celebration $celebration, GenerateGapGuidance $generate): JsonResponse
    {
        abort_if($celebration->published_at === null || ! $celebration->is_active, 404);

        $guidance = $generate->handle($celebration, $request->user());

        return response()->json(['data' => $guidance->only(['intro', 'actions', 'source'])]);
    }

    public function contentViewed(Request $request, ContentHubItem $item, RecordActivity $activity): JsonResponse
    {
        // Watch/listen tracking feeds the engagement score (PRD §6.19).
        $activity->handle($request->user(), ActivityType::ContentViewed, $item);

        return response()->json(['data' => ['ok' => true]]);
    }
}
