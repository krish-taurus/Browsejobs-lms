<?php

declare(strict_types=1);

namespace App\Http\Controllers\Interviews;

use App\Actions\Interviews\ApplyForInterview;
use App\Http\Controllers\Controller;
use App\Jobs\NotifyPanelOfInterviewApplication;
use App\Models\MentorProfile;
use App\Support\Interviews\InterviewPanel;
use App\Support\Mentoring\SlotFinder;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Student side of the two-round placement interview (Round 1 with a Tech
 * Mentor, final round with the Tech Manager).
 *
 * Distinct from the AI mock interview at /mock: this books time with a real
 * person, and the slot is a request until someone on the panel approves it.
 */
final class InterviewController extends Controller
{
    public function __construct(
        private readonly ApplyForInterview $apply,
        private readonly SlotFinder $slots,
    ) {}

    /** Both rounds, where the student stands, and who is free when. */
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $state = InterviewPanel::stateFor($request->user()->id);

            $panel = [];
            foreach ([MentorProfile::ROUND_SCREENING, MentorProfile::ROUND_FINAL] as $round) {
                $panel[$round] = InterviewPanel::forRound($round)->map(fn (MentorProfile $m) => [
                    'id' => $m->id,
                    'name' => $m->user?->name,
                    'headline' => $m->headline,
                    // The calendar the student picks from. Falls back to an
                    // empty list rather than erroring when nobody has set
                    // availability yet — the page then says so plainly.
                    'slots' => $this->slotsFor($m),
                ])->values();
            }

            return response()->json(['data' => [
                'cleared_round_one' => $state['cleared_round_one'],
                'rounds' => $state['rounds'],
                'panel' => $panel,
                'duration_minutes' => (int) config('interviews.duration_minutes', 45),
                'min_notice_hours' => (int) config('interviews.min_notice_hours', 12),
            ]]);
        });
    }

    /** Apply for a slot. Creates a pending request, never a confirmed booking. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'round' => ['required', 'integer', 'in:1,2'],
            'mentor_profile_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $validated): JsonResponse {
            try {
                $session = $this->apply->handle(
                    $request->user(),
                    (int) $validated['round'],
                    (int) $validated['mentor_profile_id'],
                    // The browser sends a UTC instant. This app stores wall time
                    // in app.timezone (IST), so it has to be converted before it
                    // is saved — writing the UTC clock straight into an IST
                    // column silently books the interview 5.5 hours early.
                    CarbonImmutable::parse($validated['starts_at'])
                        ->setTimezone(config('app.timezone')),
                );
            } catch (ValidationException $e) {
                throw $e;
            }

            // Panel alert (WhatsApp) goes out in the background so a slow
            // WhatsApp API never makes the student's Apply button hang.
            NotifyPanelOfInterviewApplication::dispatch($session->id);

            return response()->json([
                'data' => InterviewPanel::stateFor($request->user()->id),
                'message' => 'Applied. The panel will confirm your slot shortly — you will get a WhatsApp once it is approved.',
            ], 201);
        });
    }

    /**
     * Free slots for one interviewer, or an empty list when availability has
     * not been set up yet.
     *
     * @return list<string>
     */
    private function slotsFor(MentorProfile $mentor): array
    {
        try {
            return $this->slots->slotsFor($mentor, (int) config('interviews.booking_window_days', 21))
                ->map(fn ($slot) => (string) $slot)
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
