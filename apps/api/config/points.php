<?php

declare(strict_types=1);

/*
| Points & badges (PRD §6.16). Weights live per-tenant in points_settings
| (admin-editable); these are only the row defaults. Badge labels are shown on
| the student leaderboard card and in celebration notifications.
*/
return [
    'defaults' => [
        'attendance_points' => (int) env('POINTS_ATTENDANCE', 20),
        'punctuality_points' => (int) env('POINTS_PUNCTUALITY', 5),
        'quiz_points' => (int) env('POINTS_QUIZ', 30),
        'lab_points' => (int) env('POINTS_LAB', 25),
        'streak_day_points' => (int) env('POINTS_STREAK_DAY', 10),
        'mock_points' => (int) env('POINTS_MOCK', 40),
        'daily_cap' => (int) env('POINTS_DAILY_CAP', 150),
        'attendance_min_pct' => (int) env('POINTS_ATTENDANCE_MIN_PCT', 60),
    ],

    'badges' => [
        'seven-day-streak' => '7-Day Streak',
        'module-ace' => 'Module Ace',
        'first-lab-pass' => 'First Lab Pass',
        'first-mock-done' => 'First Mock Done', // reserved for P4
    ],

    /** How many active batchmates get the celebration notification. */
    'celebration_fanout_limit' => 50,
];
