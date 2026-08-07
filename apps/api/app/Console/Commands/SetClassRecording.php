<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveClasses\RegisterCompletedRecording;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use Illuminate\Console\Command;

/**
 * Attaches a recording play URL to a class so absentees can watch it from the
 * student portal — the manual fallback when the Zoom recording webhook did not
 * deliver (or the class was recorded elsewhere). Driven by the CRM.
 */
final class SetClassRecording extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'class:recording
        {session : Live session id}
        {url : Play URL (Zoom cloud share link or any streamable URL)}
        {--title= : Recording title (defaults to the class title)}
        {--passcode= : Zoom share passcode, if any}';

    protected $description = 'Attach a recording URL to a class so students can replay it';

    public function handle(RegisterCompletedRecording $register): int
    {
        $session = $this->findSession((string) $this->argument('session'));

        if ($session === null) {
            $this->error("Session '{$this->argument('session')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($session->tenant_id, function () use ($session, $register): int {
            $recording = $register->handle(
                $session,
                (string) ($this->option('title') ?: $session->title),
                (string) $this->argument('url'),
                $this->option('passcode') ?: null,
            );

            $this->info("Recording #{$recording->id} stored for class #{$session->id} — students can now replay it.");

            return self::SUCCESS;
        });
    }
}
