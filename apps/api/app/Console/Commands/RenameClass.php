<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use Illuminate\Console\Command;

/**
 * Rename a class. A series scheduled without syllabus titles lands as
 * "Class 1 … Class 91", which tells a student nothing about what the day
 * covers; this is how the CRM gives those rows a real name.
 *
 * Title only. Nothing is rescheduled, no Zoom call is touched and no student
 * is messaged — a class whose name is corrected has not changed, so telling
 * the batch about it would be noise. The Zoom meeting keeps the topic it was
 * created with; ZoomClient::updateMeeting only carries a time and a duration.
 */
final class RenameClass extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'class:rename
        {session : Live session id}
        {title : New class title}';

    protected $description = 'Rename a class (title only — no Zoom change, nobody notified)';

    public function handle(): int
    {
        $session = $this->findSession((string) $this->argument('session'));

        if ($session === null) {
            $this->error("Session '{$this->argument('session')}' not found.");

            return self::FAILURE;
        }

        $title = trim((string) $this->argument('title'));

        if ($title === '') {
            $this->error('A class needs a title.');

            return self::FAILURE;
        }

        return $this->runForTenant($session->tenant_id, function () use ($session, $title): int {
            $previous = (string) $session->title;

            if ($previous === $title) {
                $this->info("Class #{$session->id} is already called \"{$title}\".");

                return self::SUCCESS;
            }

            $session->forceFill(['title' => $title])->save();

            $this->info("Class #{$session->id} renamed from \"{$previous}\" to \"{$title}\".");

            return self::SUCCESS;
        });
    }
}
