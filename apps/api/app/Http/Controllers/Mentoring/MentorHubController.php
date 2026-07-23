<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mentoring;

use App\Actions\Mentoring\MarkMentorNoShow;
use App\Actions\Mentoring\SubmitMentorFeedback;
use App\Http\Controllers\Controller;
use App\Models\MentorAvailability;
use App\Models\MentorAvailabilityException;
use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The mentor's own workspace (PRD §6.11): their sessions (upcoming +
 * awaiting feedback), post-session feedback, no-show marking, and their own
 * availability editor. Access = owning an active mentor profile — no extra
 * permission, ownership is the gate.
 */
final class MentorHubController extends Controller
{
    public function __construct(
        private readonly SubmitMentorFeedback $feedback,
        private readonly MarkMentorNoShow $noShow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $profile = $this->profile($request);

            $sessions = MentorSession::query()
                ->where('mentor_profile_id', $profile->id)
                ->with('student:id,name,email,phone')
                ->orderBy('starts_at')
                ->limit(100)
                ->get();

            return response()->json(['data' => [
                'profile' => [
                    'id' => $profile->id,
                    'headline' => $profile->headline,
                    'expertise' => $profile->expertise_tags,
                    'rating' => $profile->averageRating(),
                ],
                'upcoming' => $sessions
                    ->where('status', MentorSession::STATUS_BOOKED)
                    ->filter(fn (MentorSession $s) => $s->starts_at->isFuture())
                    ->map(fn (MentorSession $s) => $this->row($s))->values(),
                'needs_action' => $sessions
                    ->where('status', MentorSession::STATUS_BOOKED)
                    ->filter(fn (MentorSession $s) => $s->starts_at->isPast())
                    ->map(fn (MentorSession $s) => $this->row($s))->values(),
                'past' => $sessions
                    ->whereIn('status', [MentorSession::STATUS_COMPLETED, MentorSession::STATUS_NO_SHOW, MentorSession::STATUS_CANCELLED])
                    ->sortByDesc('starts_at')->take(20)
                    ->map(fn (MentorSession $s) => $this->row($s))->values(),
            ]]);
        });
    }

    public function submitFeedback(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'strengths' => ['required', 'string', 'max:2000'],
            'improvements' => ['required', 'string', 'max:2000'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $session, $validated): JsonResponse {
            $model = $this->ownedSession($request, $session);
            $this->feedback->handle($model, $validated);

            return response()->json(['data' => $this->row($model->refresh())]);
        });
    }

    public function markNoShow(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate(['side' => ['required', 'in:student,mentor']]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $session, $validated): JsonResponse {
            $model = $this->ownedSession($request, $session);
            $this->noShow->handle($model, $validated['side']);

            return response()->json(['data' => $this->row($model->refresh())]);
        });
    }

    public function availability(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $profile = $this->profile($request);

            return response()->json(['data' => $this->availabilityPayload($profile)]);
        });
    }

    /** Replace-all editor: the submitted windows/exceptions become the truth. */
    public function updateAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'windows' => ['present', 'array', 'max:30'],
            'windows.*.weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'windows.*.start_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'windows.*.end_minute' => ['required', 'integer', 'gt:windows.*.start_minute', 'max:1440'],
            'exceptions' => ['present', 'array', 'max:60'],
            'exceptions.*.date' => ['required', 'date'],
            'exceptions.*.start_minute' => ['nullable', 'integer', 'min:0', 'max:1439'],
            'exceptions.*.end_minute' => ['nullable', 'integer', 'max:1440', 'required_with:exceptions.*.start_minute'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $validated): JsonResponse {
            $profile = $this->profile($request);

            DB::transaction(function () use ($profile, $validated): void {
                MentorAvailability::query()->where('mentor_profile_id', $profile->id)->delete();
                MentorAvailabilityException::query()->where('mentor_profile_id', $profile->id)->delete();

                foreach ($validated['windows'] as $w) {
                    MentorAvailability::query()->create([
                        'tenant_id' => $profile->tenant_id, 'mentor_profile_id' => $profile->id,
                        'weekday' => $w['weekday'], 'start_minute' => $w['start_minute'], 'end_minute' => $w['end_minute'],
                    ]);
                }

                foreach ($validated['exceptions'] as $e) {
                    MentorAvailabilityException::query()->create([
                        'tenant_id' => $profile->tenant_id, 'mentor_profile_id' => $profile->id,
                        'date' => $e['date'],
                        'start_minute' => $e['start_minute'] ?? null,
                        'end_minute' => $e['end_minute'] ?? null,
                    ]);
                }
            });

            return response()->json(['data' => $this->availabilityPayload($profile->refresh())]);
        });
    }

    private function profile(Request $request): MentorProfile
    {
        $profile = MentorProfile::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        abort_if($profile === null, 403, 'You do not have a mentor profile.');

        return $profile;
    }

    private function ownedSession(Request $request, int $id): MentorSession
    {
        return MentorSession::query()
            ->where('mentor_profile_id', $this->profile($request)->id)
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function availabilityPayload(MentorProfile $profile): array
    {
        return [
            'windows' => $profile->availabilities()->orderBy('weekday')->orderBy('start_minute')
                ->get(['id', 'weekday', 'start_minute', 'end_minute']),
            'exceptions' => $profile->exceptions()->orderBy('date')
                ->get(['id', 'date', 'start_minute', 'end_minute']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(MentorSession $s): array
    {
        $booked = $s->status === MentorSession::STATUS_BOOKED;

        return [
            'id' => $s->id,
            'student' => $s->student?->name,
            // Direct connect (ADR 0043): the mentor reaches out at the slot, so a
            // booked session carries the student's contact instead of a Zoom link.
            'student_phone' => $booked ? $s->student?->phone : null,
            'student_email' => $booked ? $s->student?->email : null,
            'purpose' => $s->purpose,
            'status' => $s->status,
            'no_show_side' => $s->no_show_side,
            'starts_at' => $s->starts_at->clone()->utc()->toIso8601String(),
            // Legacy sessions booked before the direct-connect change may still
            // have a meeting; new ones never do.
            'join_url' => $booked ? $s->start_url ?? $s->join_url : null,
            'feedback' => $s->feedback,
            'student_rating' => $s->student_rating,
        ];
    }
}
