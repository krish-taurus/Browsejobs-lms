<?php

declare(strict_types=1);

use App\Jobs\SendDailyBrief;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Dunning ladder (PRD §6.8): daily reminders + soft/hard access blocks.
Schedule::command('fees:run-ladder')->dailyAt('07:00');

// Bootcamp-conversion nudge ladder (PRD §5 Stage 3): daily non-payer nudges.
Schedule::command('conversions:run-nudges')->dailyAt('08:00');

// Masterclass → free bootcamp auto-invite (funnel Stage 2→3): daily.
Schedule::command('bootcamp:invite')->dailyAt('09:00');

// Automated enrolment funnel: build the weekend masterclass batch per course
// from lead interest, roll each finished masterclass into a 7-day bootcamp
// (with its paid batch pre-linked), and convert ended bootcamps to paid.
// Idempotent daily sweep.
Schedule::command('funnel:advance')->dailyAt('05:15');

// Support-ticket SLA sweep (PRD §6.13): safety net behind the delayed per-ticket jobs.
Schedule::command('support:check-sla')->hourly();

// Post-class study nudge (PRD §6 daily loop): nudge cohorts to review flashcards
// for classes that have ended, once the flashcards exist. Idempotent.
Schedule::command('class:wrapup')->hourly();

// Google Drive review intake (Platform Spec §3): pull new review images from the
// configured folder into the triage queue. Weekly; a no-op until a service account
// is configured. Idempotent (dedup by Drive file id).
Schedule::command('reviews:sync-drive')->weeklyOn(1, '05:30');

// Student rescore (PRD §6.4): nightly mastery/engagement/risk/PRI + snapshots.
Schedule::command('scores:recompute')->dailyAt('06:00');

// AI Tutor knowledge base (PRD §6.4): weekly rebuild from course/program/lab content.
Schedule::command('tutor:reindex')->weeklyOn(1, '05:00');

// Quiz-dispatch reminder ladder (PRD §6.5): safety net for 48h reminders / 96h flags.
Schedule::command('quizzes:check-dispatch')->hourly();

// Weekly AI student reports (PRD §6.10): Monday morning, after Sunday's scores.
Schedule::command('reports:weekly')->weeklyOn(1, '07:30');

// Counselor daily risk digest (PRD §6.10): after the nightly rescore.
Schedule::command('digest:counselor-daily')->dailyAt('06:30');

// Weekly support themes to admin (PRD §6.13): Monday, covering the week just closed.
Schedule::command('digest:support-themes')->weeklyOn(1, '08:00');

// P3.7 — Market Pulse (PRD §6.19): daily digest build + weekly opt-in send.
Schedule::command('digest:market-pulse')->dailyAt('06:45');
Schedule::command('pulse:weekly-send')->weeklyOn(1, '10:00');

// P4.6a review protection & retention (PRD §6.20).
Schedule::command('care:dispatch')->dailyAt('09:00');
Schedule::command('care:signals')->dailyAt('06:15');

// P4.7 Curriculum Intelligence (PRD §6.21): quarterly syllabus recommendations.
Schedule::command('curriculum:recommend')->quarterly();

// P4.7c Advice Graph (PRD §6.21): daily alumni 6/12-month check-in scheduling.
Schedule::command('alumni:checkins')->dailyAt('07:15');

// P4.7d Advice Graph (PRD §6.21): monthly PRI weight calibration from outcomes.
Schedule::command('pri:calibrate')->monthlyOn(1, '05:30');

// P4.8 Live Job Feed (PRD §6.22): sync sources + expire stale items, twice daily.
Schedule::command('feed:sync')->twiceDaily(6, 18);

// P4.8b "Jobs for You": daily nudge about new well-matched roles.
Schedule::command('jobs:nudge')->dailyAt('09:30');

// P4.8c Apply Assist: daily follow-up nudges on stalled applications.
Schedule::command('applications:follow-ups')->dailyAt('10:00');

// Market intelligence (landing boards): refresh at midnight so the boards
// carry the day's figures from the moment the date changes.
Schedule::command('market:refresh')->dailyAt('00:00');

// Daily Market Brief teaser to open leads, after the morning snapshot refresh.
Schedule::job(new SendDailyBrief)->dailyAt('08:30');
