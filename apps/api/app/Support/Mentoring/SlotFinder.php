<?php

declare(strict_types=1);

namespace App\Support\Mentoring;

use App\Models\MentorProfile;
use App\Models\MentorSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Computes bookable slots (PRD §6.11): weekly IST windows, minus dated
 * exceptions, minus already-booked sessions, minus the notice lead time —
 * per mentor, or combined across every active mentor (optionally filtered
 * by expertise tag). Deterministic and side-effect free; booking re-checks
 * through the same math so the calendar can never show what booking rejects.
 */
final class SlotFinder
{
    /**
     * Combined availability calendar. When $courseIds is given, only mentors
     * covering one of those courses (or generalists) appear — a DevOps
     * mentor's slots never reach a Data Engineering student.
     *
     * @param  list<int>|null  $courseIds
     * @return list<array{mentor_profile_id: int, mentor: string, expertise: list<string>, starts_at: string}>
     */
    public function combined(?string $tag = null, ?int $days = null, ?array $courseIds = null): array
    {
        return MentorProfile::query()
            ->where('is_active', true)
            ->with(['user:id,name', 'availabilities', 'exceptions'])
            ->get()
            ->filter(fn (MentorProfile $mentor) => $courseIds === null || $mentor->coversAnyCourse($courseIds))
            ->filter(fn (MentorProfile $mentor) => $tag === null || in_array(
                mb_strtolower($tag),
                array_map(mb_strtolower(...), $mentor->expertise_tags),
                true,
            ))
            ->flatMap(fn (MentorProfile $mentor) => $this->slotsFor($mentor, $days)->map(fn (CarbonImmutable $slot) => [
                'mentor_profile_id' => $mentor->id,
                'mentor' => (string) $mentor->user?->name,
                'expertise' => $mentor->expertise_tags,
                'starts_at' => $slot->toIso8601String(),
            ]))
            ->sortBy('starts_at')
            ->values()
            ->all();
    }

    public function isBookable(MentorProfile $mentor, CarbonImmutable $startsAt, ?int $days = null): bool
    {
        return $this->slotsFor($mentor, $days)->contains(fn (CarbonImmutable $slot) => $slot->equalTo($startsAt));
    }

    /**
     * All bookable slot starts (UTC) for one mentor over the window.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function slotsFor(MentorProfile $mentor, ?int $days = null): Collection
    {
        $tz = (string) config('mentoring.timezone', 'Asia/Kolkata');
        $step = (int) config('mentoring.slot_minutes', 30);
        $days ??= (int) config('mentoring.window_days', 14);
        $earliest = CarbonImmutable::now()->addHours((int) config('mentoring.cancel_notice_hours', 4));

        $mentor->loadMissing(['availabilities', 'exceptions']);

        $busy = MentorSession::query()
            ->where('mentor_profile_id', $mentor->id)
            ->where('status', MentorSession::STATUS_BOOKED)
            ->where('starts_at', '>=', now())
            ->pluck('starts_at')
            ->map(fn ($t) => CarbonImmutable::parse($t)->utc()->toIso8601String())
            ->flip();

        $slots = collect();
        $today = CarbonImmutable::now($tz)->startOfDay();

        for ($d = 0; $d < $days; $d++) {
            $date = $today->addDays($d);
            $windows = $mentor->availabilities->where('weekday', $date->dayOfWeek);
            if ($windows->isEmpty()) {
                continue;
            }

            $dayExceptions = $mentor->exceptions->filter(fn ($e) => $e->date->toDateString() === $date->toDateString());
            if ($dayExceptions->contains(fn ($e) => $e->isFullDay())) {
                continue;
            }

            foreach ($windows as $window) {
                for ($minute = (int) $window->start_minute; $minute + $step <= (int) $window->end_minute; $minute += $step) {
                    $blocked = $dayExceptions->contains(fn ($e) => ! $e->isFullDay()
                        && $minute < (int) $e->end_minute
                        && $minute + $step > (int) $e->start_minute);
                    if ($blocked) {
                        continue;
                    }

                    $start = $date->addMinutes($minute)->utc();
                    if ($start->lt($earliest) || $busy->has($start->toIso8601String())) {
                        continue;
                    }

                    $slots->push($start);
                }
            }
        }

        return $slots->unique(fn (CarbonImmutable $s) => $s->timestamp)->sort()->values();
    }
}
