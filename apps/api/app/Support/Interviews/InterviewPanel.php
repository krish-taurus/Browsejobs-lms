<?php

declare(strict_types=1);

namespace App\Support\Interviews;

use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who takes which interview round, and what each round is called.
 *
 * The panel is data (mentor_profiles.interview_round, seeded from
 * config/interviews.php), so nothing here hardcodes a person. Round labels live
 * here too, because the student page, the WhatsApp copy and the CRM screen must
 * all call round 2 the same thing.
 */
final class InterviewPanel
{
    /**
     * Active interviewers for a round.
     *
     * @return Collection<int, MentorProfile>
     */
    public static function forRound(int $round): Collection
    {
        return MentorProfile::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->where('interview_round', $round)
            // tenant_id is not decoration: Messenger resolves the WhatsApp
            // template by the recipient's tenant, so leaving it out of this
            // select made every panel alert resolve to no template and
            // return silently, with nothing logged and no error raised.
            ->with('user:id,name,phone,tenant_id')
            ->orderBy('id')
            ->get();
    }

    /** Human name for a round, e.g. for WhatsApp copy and headings. */
    public static function stageLabel(int $round): string
    {
        return match ($round) {
            MentorProfile::ROUND_SCREENING => 'Tech Mentor screen',
            MentorProfile::ROUND_FINAL => 'Final round with the Tech Manager',
            default => 'Interview',
        };
    }

    /**
     * A slot time as a person reads it. Everything in this platform runs on
     * IST, and a student should never have to convert a timezone to work out
     * when their own interview is.
     */
    public static function slotLabel(?\DateTimeInterface $at): string
    {
        if ($at === null) {
            return 'a time to be confirmed';
        }

        return Carbon::instance(
            \DateTimeImmutable::createFromInterface($at)
        )->timezone('Asia/Kolkata')->format('D, d M Y · g:i A').' IST';
    }

    /** Short label for chips and tables. */
    public static function shortLabel(int $round): string
    {
        return $round === MentorProfile::ROUND_FINAL ? 'Final round' : 'Round 1';
    }

    /**
     * The student's own interview state, in the shape both the portal page and
     * the CRM screen read. One definition of "where is this person up to".
     *
     * @return array{cleared_round_one: bool, rounds: list<array<string, mixed>>}
     */
    public static function stateFor(int $studentId): array
    {
        $sessions = MentorSession::query()
            ->where('student_id', $studentId)
            ->whereNotNull('interview_round')
            ->with('mentor.user:id,name')
            ->orderByDesc('starts_at')
            ->get();

        $clearedOne = $sessions->contains(
            fn (MentorSession $s) => $s->interview_round === 1 && $s->outcome === MentorSession::OUTCOME_CLEARED
        );

        $rounds = [];

        foreach ([MentorProfile::ROUND_SCREENING, MentorProfile::ROUND_FINAL] as $round) {
            $latest = $sessions->firstWhere('interview_round', $round);

            // Round 2 stays shut until round 1 comes back cleared.
            $unlocked = $round === MentorProfile::ROUND_SCREENING || $clearedOne;

            $rounds[] = [
                'round' => $round,
                'label' => self::shortLabel($round),
                'stage' => self::stageLabel($round),
                'unlocked' => $unlocked,
                'locked_reason' => $round === MentorProfile::ROUND_FINAL && ! $clearedOne
                    ? 'Clear Round 1 first — the final round opens once a Tech Mentor passes you.'
                    : null,
                'session' => self::sessionPayload($latest, $unlocked),
            ];
        }

        return ['cleared_round_one' => $clearedOne, 'rounds' => $rounds];
    }

    /**
     * One round's booking, as the portal and the CRM both read it.
     *
     * @return array<string, mixed>|null
     */
    private static function sessionPayload(?MentorSession $s, bool $unlocked): ?array
    {
        if ($s === null) {
            return null;
        }

        return [
            'id' => $s->id,
            'starts_at' => $s->starts_at?->toIso8601String(),
            'ends_at' => $s->endsAt()?->toIso8601String(),
            'requested_starts_at' => $s->requested_starts_at?->toIso8601String(),
            'duration_minutes' => $s->duration_minutes,
            'approval_status' => $s->approval_status,
            'outcome' => $s->outcome,
            'review_note' => $s->review_note,
            'interviewer' => $s->mentor?->user?->name,

            // The slot came and went and nobody recorded a verdict. The portal
            // stops calling it confirmed, and the student may book again.
            'lapsed' => $s->hasLapsed(),

            // A locked round must not hand out its Zoom link, and neither must
            // an interview that is already over. Hiding the button in the
            // portal would still leave the URL sitting in this payload for
            // anyone who reads the API response.
            'join_url' => $s->approval_status === MentorSession::APPROVAL_APPROVED
                && $unlocked
                && $s->canStillJoin()
                ? $s->join_url
                : null,
        ];
    }
}
