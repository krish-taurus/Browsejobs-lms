<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

/** A proposal before it is saved: what kind, which role, the exact rows, and the sentence the client sees. */
final readonly class PlannedAction
{
    /** @param  array<string, mixed>  $params */
    public function __construct(
        public string $kind,
        public int $jobId,
        public array $params,
        public string $summary,
    ) {}
}
