<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Sets (or clears) the recorded masterclass for a course — the video the
 * public "watch it live" page streams as a daily simulated-live showing.
 * Driven by the CRM Masterclasses page.
 */
final class SetMasterclassVideo extends Command
{
    protected $signature = 'course:set-masterclass-video
        {course : Course slug}
        {url : YouTube link or direct MP4 URL — pass "clear" to remove}';

    protected $description = "Set the course's recorded masterclass video for the daily simulated-live showing";

    public function handle(): int
    {
        $course = Course::query()->withoutGlobalScope(TenantScope::class)
            ->where('slug', $this->argument('course'))
            ->first();

        if ($course === null) {
            $this->error("Course '{$this->argument('course')}' not found.");

            return self::FAILURE;
        }

        $url = (string) $this->argument('url');

        $course->forceFill([
            'masterclass_video_url' => $url === 'clear' ? null : $url,
        ])->save();

        $this->info($url === 'clear'
            ? "Masterclass recording cleared for {$course->name}."
            : "Masterclass recording set for {$course->name} — daily showing at ".config('funnel.masterclass_watch_time', '20:00').'.');

        return self::SUCCESS;
    }
}
