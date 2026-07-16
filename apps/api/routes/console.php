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
