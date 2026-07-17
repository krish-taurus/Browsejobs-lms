<?php

declare(strict_types=1);

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

// Support-ticket SLA sweep (PRD §6.13): safety net behind the delayed per-ticket jobs.
Schedule::command('support:check-sla')->hourly();

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
