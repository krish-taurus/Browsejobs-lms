<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MockInterview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Prints a short-lived link to watch an AI interview's recording. Recordings
 * sit on the private S3 disk; the CRM's "Watch" button runs this (it has no
 * S3 credentials of its own) and opens the link it prints.
 *
 *   php artisan mock:recording-url 132
 */
final class MockRecordingUrlCommand extends Command
{
    protected $signature = 'mock:recording-url
        {mock : Mock interview id}
        {--minutes=30 : How long the link stays valid}';

    protected $description = "Print a temporary link to an AI interview's recording.";

    public function handle(): int
    {
        $interview = MockInterview::withoutGlobalScopes()->find((int) $this->argument('mock'));
        if ($interview === null) {
            $this->error('No interview with that id.');

            return self::FAILURE;
        }

        if ($interview->recording_url === null || $interview->recording_url === '') {
            $this->error('This interview has no recording.');

            return self::FAILURE;
        }

        $minutes = max(1, min(120, (int) $this->option('minutes')));
        $url = str_starts_with($interview->recording_url, 'http')
            ? $interview->recording_url
            : Storage::disk('s3')->temporaryUrl($interview->recording_url, now()->addMinutes($minutes));

        // The link alone, so a caller can read it straight off stdout.
        $this->line($url);

        return self::SUCCESS;
    }
}
