<?php

declare(strict_types=1);

namespace App\Actions\Mentoring;

use App\Enums\EntitlementFeature;
use App\Jobs\FinalizeMentorBooking;
use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Mentoring\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books a 1:1 (PRD §6.11). The slot is re-validated through the same
 * SlotFinder math the calendar shows, the double-book race is settled under
 * a row lock, and one mentor credit is consumed inside the same transaction
 * — booking and payment can never disagree. Zoom + notifications +
 * reminders run queued (CLAUDE.md: Zoom calls are queued).
 */
final readonly class BookMentorSession
{
    public function __construct(
        private SlotFinder $slots,
        private EntitlementService $entitlements,
    ) {}

    public function handle(User $student, int $mentorProfileId, CarbonImmutable $startsAt, string $purpose): MentorSession
    {
        $mentor = MentorProfile::query()->where('is_active', true)->findOrFail($mentorProfileId);

        if (! in_array($purpose, [MentorSession::PURPOSE_MENTORING, MentorSession::PURPOSE_PLACEMENT], true)) {
            throw ValidationException::withMessages(['purpose' => 'Unknown session purpose.']);
        }

        // Relevance is enforced at booking too — the calendar filter alone
        // would leave the API open to booking any tenant mentor.
        if (! $mentor->coversAnyCourse(MentorProfile::courseIdsFor($student))) {
            throw ValidationException::withMessages(['mentor_profile_id' => 'This mentor does not cover your course.']);
        }

        if (! $this->slots->isBookable($mentor, $startsAt)) {
            throw ValidationException::withMessages(['starts_at' => 'That slot is no longer available.']);
        }

        $session = DB::transaction(function () use ($student, $mentor, $startsAt, $purpose): MentorSession {
            $taken = MentorSession::query()
                ->where('mentor_profile_id', $mentor->id)
                ->where('status', MentorSession::STATUS_BOOKED)
                ->where('starts_at', $startsAt)
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['starts_at' => 'That slot was just booked by someone else.']);
            }

            // Throws when the wallet is empty — the controller answers 402
            // with the mentor-extra top-up.
            $this->entitlements->consumeCredits($student, EntitlementFeature::Mentor->value, 1, 'mentor_session');

            return MentorSession::query()->create([
                'tenant_id' => $student->tenant_id,
                'mentor_profile_id' => $mentor->id,
                'student_id' => $student->id,
                'purpose' => $purpose,
                'status' => MentorSession::STATUS_BOOKED,
                'starts_at' => $startsAt,
                'duration_minutes' => (int) config('mentoring.slot_minutes', 30),
            ]);
        });

        FinalizeMentorBooking::dispatch($session->id);

        return $session;
    }
}
