<?php

declare(strict_types=1);

namespace App\Support\Scoring;

/**
 * Rule-based counselor intervention scripts (PRD §6.10 counselor digest), keyed to
 * a student's top red flag. Canned, not AI-drafted (this milestone). The counselor
 * makes the call (§7).
 */
final class InterventionScript
{
    /**
     * @param  list<string>  $redFlags
     */
    public function for(array $redFlags): string
    {
        return match (true) {
            in_array('fee_overdue', $redFlags, true) => "Open with empathy about the fee — the 30-day money-back guarantee still stands. Offer to walk through the EMI options and ask what's blocking payment.",
            in_array('stalled', $redFlags, true) => 'They have stalled on the curriculum. Ask what got in the way, re-anchor on their goal role, and agree one small topic to finish this week.',
            in_array('low_engagement', $redFlags, true) => 'Engagement has dropped. Check in personally, surface their last win, and set a 15-minute daily practice commitment to rebuild the streak.',
            in_array('low_attendance', $redFlags, true) => 'Attendance is slipping. Confirm the class timing works, share recordings for missed sessions, and reconfirm the next live class.',
            default => 'On track — a quick encouragement call and a nudge toward a mock interview keeps momentum.',
        };
    }
}
