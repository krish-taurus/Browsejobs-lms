<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tutor\RebuildTutorIndex;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * (Re)builds the AI Tutor knowledge base from course/program/lab content (PRD §6.4).
 * Scheduled weekly; idempotent. `--tenant=` limits to one tenant id.
 */
final class ReindexTutorKnowledge extends Command
{
    protected $signature = 'tutor:reindex {--tenant= : Limit to a single tenant id}';

    protected $description = 'Rebuild the AI Tutor knowledge base from existing content';

    public function handle(RebuildTutorIndex $action): int
    {
        $only = null;
        if ($this->option('tenant') !== null) {
            $only = Tenant::query()->find((int) $this->option('tenant'));
            if ($only === null) {
                $this->error('Tenant not found.');

                return self::FAILURE;
            }
        }

        $summary = $action->handle($only);

        $this->info("Indexed {$summary['documents']} document(s), {$summary['chunks']} chunk(s).");

        return self::SUCCESS;
    }
}
