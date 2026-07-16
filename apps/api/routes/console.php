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
