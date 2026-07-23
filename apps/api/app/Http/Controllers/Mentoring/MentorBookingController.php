<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mentoring;

use App\Actions\Mentoring\BookMentorSession;
use App\Actions\Mentoring\CancelMentorSession;
use App\Actions\Mentoring\RescheduleMentorSession;
use App\Enums\EntitlementFeature;
use App\Http\Controllers\Controller;
use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\Product;
use App\Support\Entitlements\EntitlementService;
use App\Support\Mentoring\SlotFinder;
use App\Support\Tenancy\TenantContext;
use App\Support\Time\AppTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Student side of mentor scheduling (PRD §6.11): the combined availability
 * calendar (expertise-filterable), booking (credit-gated with one-tap
 * top-up), the 4h-notice cancel/reschedule, ratings, and the .ics invite.
 * Under auth:sanctum without tenant.user — everything wraps in the
 * student's tenant context and is scoped to their user id.
 */
final class MentorBookingController extends Controller
{
    public function __construct(
        private readonly SlotFinder $slots,
        private readonly BookMentorSession $book,
        private readonly CancelMentorSession $cancel,
        private readonly RescheduleMentorSession $reschedule,
        private readonly EntitlementService $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            // Only mentors covering the student's course (or generalists) —
            // a DevOps mentor never appears for a Data Engineering student.
            $courseIds = MentorProfile::courseIdsFor($request->user());

            $mentors = MentorProfile::query()
                ->where('is_active', true)
                ->with('user:id,name')
                ->get()
                ->filter(fn (MentorProfile $m) => $m->coversAnyCourse($courseIds))
                ->values()
                ->map(fn (MentorProfile $m) => [
                    'id' => $m->id,
                    'name' => $m->user?->name,
                    'headline' => $m->headline,
                    'expertise' => $m->expertise_tags,
                    'rating' => $m->averageRating(),
                ]);

            return response()->json(['data' => [
                'mentors' => $mentors,
                'slots' => $this->slots->combined($request->query('tag'), null, $courseIds),
                'credits' => $this->entitlements->balance($request->user(), EntitlementFeature::Mentor->value),
                'topups' => $this->topups(),
            ]]);
        });
    }

    public function sessions(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $sessions = MentorSession::query()
                ->where('student_id', $request->user()->id)
                ->with('mentor.user:id,name')
                ->orderByDesc('starts_at')
                ->limit(50)
                ->get()
                ->map(fn (MentorSession $s) => $this->row($s));

            return response()->json(['data' => $sessions]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mentor_profile_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'purpose' => ['sometimes', 'string'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $validated): JsonResponse {
            try {
                $session = $this->book->handle(
                    $request->user(),
                    (int) $validated['mentor_profile_id'],
                    AppTime::parse($validated['starts_at']),
                    $validated['purpose'] ?? MentorSession::PURPOSE_MENTORING,
                );
            } catch (ValidationException $e) {
                if (str_contains($e->getMessage(), 'credits')) {
                    return response()->json([
                        'error' => [
                            'code' => 'no_mentor_credits',
                            'message' => 'You are out of mentor session credits.',
                            'topups' => $this->topups(),
                        ],
                    ], 402);
                }

                throw $e;
            }

            return response()->json(['data' => $this->row($session->load('mentor.user:id,name'))], 201);
        });
    }

    public function destroy(Request $request, int $session): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $session): JsonResponse {
            $model = $this->owned($request, $session);
            $this->cancel->handle($request->user(), $model, isStudent: true);

            return response()->json(['data' => $this->row($model->refresh())]);
        });
    }

    public function move(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate(['starts_at' => ['required', 'date']]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $session, $validated): JsonResponse {
            $model = $this->owned($request, $session);
            $this->reschedule->handle($model, AppTime::parse($validated['starts_at']));

            return response()->json(['data' => $this->row($model->refresh())]);
        });
    }

    public function rate(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $session, $validated): JsonResponse {
            $model = $this->owned($request, $session);

            abort_if($model->starts_at->isFuture(), 422, 'You can rate a session after it happens.');

            $model->update([
                'student_rating' => $validated['rating'],
                'student_comment' => $validated['comment'] ?? null,
            ]);

            return response()->json(['data' => $this->row($model->refresh())]);
        });
    }

    /** RFC 5545 invite — plain text, no package needed. */
    public function ics(Request $request, int $session): Response
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $session): Response {
            $model = $this->owned($request, $session);
            $model->loadMissing('mentor.user');

            $label = $model->purpose === MentorSession::PURPOSE_PLACEMENT ? 'Placement interview' : 'Mentor session';
            $start = $model->starts_at->copy()->utc()->format('Ymd\THis\Z');
            $end = $model->starts_at->copy()->addMinutes($model->duration_minutes)->utc()->format('Ymd\THis\Z');

            // Direct connect (ADR 0043): no meeting link — the mentor reaches
            // out at the slot. Legacy sessions may still carry a join URL.
            $description = $model->join_url !== null
                ? "Join: {$model->join_url}"
                : 'Your mentor will connect with you directly at the scheduled time.';

            $ics = implode("\r\n", [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//BrowseJobs//Mentoring//EN',
                'BEGIN:VEVENT',
                "UID:mentor-session-{$model->id}@browsejobs.ai",
                "DTSTAMP:{$start}",
                "DTSTART:{$start}",
                "DTEND:{$end}",
                "SUMMARY:{$label} with {$model->mentor?->user?->name}",
                "DESCRIPTION:{$description}",
                $model->join_url !== null ? "URL:{$model->join_url}" : 'URL:',
                'END:VEVENT',
                'END:VCALENDAR',
                '',
            ]);

            return response($ics, 200, [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => "attachment; filename=mentor-session-{$model->id}.ics",
            ]);
        });
    }

    private function owned(Request $request, int $id): MentorSession
    {
        return MentorSession::query()
            ->where('student_id', $request->user()->id)
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(MentorSession $s): array
    {
        return [
            'id' => $s->id,
            'mentor' => $s->mentor?->user?->name,
            'purpose' => $s->purpose,
            'status' => $s->status,
            'starts_at' => $s->starts_at->clone()->utc()->toIso8601String(),
            'duration_minutes' => $s->duration_minutes,
            'join_url' => $s->status === MentorSession::STATUS_BOOKED ? $s->join_url : null,
            'feedback' => $s->feedback,
            'student_rating' => $s->student_rating,
            'can_change' => $s->status === MentorSession::STATUS_BOOKED && $s->withinNoticeWindow(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topups(): array
    {
        return Product::query()
            ->where('feature', EntitlementFeature::Mentor->value)
            ->where('active', true)
            ->orderBy('price_paise')
            ->get(['id', 'sku', 'name', 'price_paise', 'grant_amount'])
            ->map(fn (Product $p) => [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'price_paise' => $p->price_paise,
                'sessions' => $p->grant_amount,
            ])
            ->all();
    }
}
