<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

/**
 * The normalised shape every adapter returns (PRD §6.22). Source-agnostic so
 * ingestion, dedupe, and skill extraction are identical regardless of origin.
 */
final readonly class NormalizedJob
{
    public function __construct(
        public string $title,
        public string $company,
        public string $description,
        public ?string $externalId = null,
        public ?string $location = null,
        public ?string $workMode = null,
        public ?string $applyUrl = null,
        public ?string $roleTitle = null,
        public ?string $seniority = null,
        public ?\DateTimeInterface $postedAt = null,
    ) {}
}
