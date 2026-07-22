<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Models\FlashcardReview;
use App\Support\Learning\Sm2;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A student's flashcard review surface for a lesson. `deck` returns the lesson's
 * cards ordered due-first (new cards are due immediately); `review` records a
 * rating and reschedules the card with SM-2 spaced repetition.
 */
final class MyFlashcardController extends Controller
{
    /** The lesson's cards with this student's schedule, due cards first. */
    public function deck(Request $request, int $lesson): JsonResponse
    {
        $user = $request->user();

        return app(TenantContext::class)->run($user->tenant, function () use ($user, $lesson): JsonResponse {
            $cards = Flashcard::query()->where('lesson_id', $lesson)->orderBy('position')->orderBy('id')->get();
            $reviews = FlashcardReview::query()
                ->where('user_id', $user->id)
                ->whereIn('flashcard_id', $cards->pluck('id'))
                ->get()->keyBy('flashcard_id');

            $now = now();
            $data = $cards->map(function (Flashcard $card) use ($reviews, $now): array {
                $review = $reviews->get($card->id);
                $dueAt = $review?->due_at;

                return [
                    'id' => $card->id,
                    'front' => $card->front,
                    'back' => $card->back,
                    'due' => $dueAt === null || $dueAt->lte($now), // new or overdue
                    'due_at' => $dueAt?->toIso8601String(),
                ];
            })
                ->sortBy(fn (array $c) => [$c['due'] ? 0 : 1, $c['due_at'] ?? ''])
                ->values();

            return response()->json([
                'data' => $data,
                'meta' => ['due_count' => $data->where('due', true)->count(), 'total' => $data->count()],
            ]);
        });
    }

    /** Record a rating and reschedule the card (SM-2). */
    public function review(Request $request, int $flashcard): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', Rule::in(array_keys(Sm2::RATINGS))],
        ]);
        $user = $request->user();

        return app(TenantContext::class)->run($user->tenant, function () use ($user, $flashcard, $validated): JsonResponse {
            $card = Flashcard::query()->findOrFail($flashcard);

            $review = FlashcardReview::query()->firstOrNew(
                ['user_id' => $user->id, 'flashcard_id' => $card->id],
                ['tenant_id' => $card->tenant_id, 'ease' => Sm2::EASE_DEFAULT, 'interval_days' => 0, 'reps' => 0, 'lapses' => 0],
            );

            $next = Sm2::schedule((float) $review->ease, (int) $review->interval_days, (int) $review->reps, (int) Sm2::qualityFor($validated['rating']));

            $review->fill([
                'ease' => $next['ease'],
                'interval_days' => $next['interval_days'],
                'reps' => $next['reps'],
                'lapses' => $review->lapses + ($next['lapsed'] ? 1 : 0),
                'due_at' => now()->addDays($next['interval_days']),
                'last_reviewed_at' => now(),
            ])->save();

            return response()->json(['data' => [
                'flashcard_id' => $card->id,
                'due_at' => $review->due_at?->toIso8601String(),
                'interval_days' => $review->interval_days,
            ]]);
        });
    }
}
