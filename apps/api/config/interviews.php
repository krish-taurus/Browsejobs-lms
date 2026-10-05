<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The interview panel
    |--------------------------------------------------------------------------
    |
    | Who takes which round. Round 1 is the Tech Mentor screen; round 2 is the
    | Tech Manager's final call, and only opens once round 1 came back cleared.
    |
    | The email is the identity. `interviews:sync-panel` matches on it, creates
    | the LMS staff account if there isn't one, and keeps the mentor profile in
    | step — so adding an interviewer is an entry here plus one command, never
    | a schema change.
    |
    | These people work in the CRM, not the LMS. The LMS account exists to own
    | the mentor profile and to name the interviewer on a booking; nobody is
    | expected to sign in with it. The phone is what the student's application
    | alert is sent to.
    |
    | This list IS the students' interviewer dropdown. To take someone off it,
    | delete their entry and re-run `interviews:sync-panel` — that deactivates
    | their profile and they stop taking new slots, but every booking and
    | verdict they already have is left alone. Paste the entry back and re-run
    | to return them.
    |
    */
    'panel' => [
        [
            'name' => 'Anjali Ambastha',
            'email' => 'anjali.ambastha@browsejobs.in',
            'phone' => '9572826178',
            'round' => 1,
            'headline' => 'Tech Mentor',
        ],
        [
            'name' => 'Somali Bisoi',
            'email' => 'somali@browsejobs.in',
            'phone' => '8431814749',
            'round' => 2,
            'headline' => 'Tech Manager',
        ],
    ],

    /** How long an interview slot runs. */
    'duration_minutes' => (int) env('INTERVIEW_DURATION_MINUTES', 45),

    /** A student cannot apply for a slot closer than this. */
    'min_notice_hours' => (int) env('INTERVIEW_MIN_NOTICE_HOURS', 12),

    /** How far ahead the student may book. */
    'booking_window_days' => (int) env('INTERVIEW_BOOKING_WINDOW_DAYS', 21),

    /**
     * How long the Join button stays open after a slot has ended.
     *
     * Interviews run over, and somebody rejoining after a dropped call should
     * not find the door shut. But it does shut: a live Join button on an
     * interview that finished last week is worse than no button at all.
     */
    'join_grace_minutes' => (int) env('INTERVIEW_JOIN_GRACE_MINUTES', 30),

    /**
     * How long after a slot ends before it counts as lapsed.
     *
     * The panel gets this long to record a verdict. Once it passes with no
     * outcome, the booking stops holding the student's round hostage and they
     * can apply for a new slot. It never deletes anything — the row stays for
     * the panel to settle.
     */
    'lapse_after_hours' => (int) env('INTERVIEW_LAPSE_AFTER_HOURS', 24),

    /** Where the panel goes to act — the CRM, not this app. */
    'crm_url' => env('CRM_URL', 'https://crm.browsejobs.ai'),

    /** Where the student goes to see their interview. */
    'student_url' => env('LMS_SITE_URL', 'https://browsejobs.ai').'/interviews',

];
