<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placement-team management of the mentor pool (PRD §6.11): create/update
 * profiles for staff users and watch the session pipeline (bookings,
 * no-show rates, feedback coverage). Availability stays with the mentor
 * (their own hub) — admins manage WHO mentors, mentors manage WHEN.
 */
final class MentorAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $profiles = MentorProfile::query()
            ->with('user:id,name,email')
            ->withCount([
                'sessions as booked_count' => fn ($q) => $q->where('status', MentorSession::STATUS_BOOKED),
                'sessions as completed_count' => fn ($q) => $q->where('status', MentorSession::STATUS_COMPLETED),
                'sessions as no_show_count' => fn ($q) => $q->where('status', MentorSession::STATUS_NO_SHOW),
            ])
            ->orderByDesc('is_active')
            ->get()
            ->map(fn (MentorProfile $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'headline' => $m->headline,
                'expertise' => $m->expertise_tags,
                'course_ids' => $m->course_ids ?? [],
                'is_active' => $m->is_active,
                'rating' => $m->averageRating(),
                'booked' => $m->booked_count,
                'completed' => $m->completed_count,
                'no_shows' => $m->no_show_count,
            ]);

        $sessions = MentorSession::query()
            ->with(['mentor.user:id,name', 'student:id,name'])
            ->orderByDesc('starts_at')
            ->limit(30)
            ->get()
            ->map(fn (MentorSession $s) => [
                'id' => $s->id,
                'mentor' => $s->mentor?->user?->name,
                'student' => $s->student?->name,
                'purpose' => $s->purpose,
                'status' => $s->status,
                'no_show_side' => $s->no_show_side,
                'starts_at' => $s->starts_at->clone()->utc()->toIso8601String(),
                'feedback_score' => $s->feedback_score,
                'student_rating' => $s->student_rating,
            ]);

        return response()->json(['data' => ['mentors' => $profiles, 'sessions' => $sessions]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $user = User::query()->where('user_type', 'staff')->findOrFail((int) $validated['user_id']);

        $profile = MentorProfile::query()->updateOrCreate(
            ['tenant_id' => $user->tenant_id, 'user_id' => $user->id],
            [
                'headline' => $validated['headline'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'expertise_tags' => $validated['expertise'],
                'course_ids' => $validated['course_ids'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ],
        );

        return response()->json(['data' => $profile->load('user:id,name')], 201);
    }

    public function update(Request $request, MentorProfile $mentor): JsonResponse
    {
        $validated = $this->validated($request, updating: true);
        unset($validated['user_id']); // A profile stays with its person.

        if (array_key_exists('expertise', $validated)) {
            $validated['expertise_tags'] = $validated['expertise'];
            unset($validated['expertise']);
        }

        $mentor->update($validated);

        return response()->json(['data' => $mentor->refresh()->load('user:id,name')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'user_id' => [$updating ? 'sometimes' : 'required', 'integer'],
            'headline' => ['nullable', 'string', 'max:190'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'expertise' => [$required, 'array', 'min:1', 'max:10'],
            'expertise.*' => ['string', 'max:60'],
            'course_ids' => ['sometimes', 'nullable', 'array', 'max:20'],
            'course_ids.*' => ['integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
