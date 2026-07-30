<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\VerificationStatus;
use Illuminate\Support\Carbon;

/**
 * What a provider concluded. Immutable, and carries only what an employer is
 * entitled to see: the fact of a match and its provenance, never the raw
 * document behind it.
 */
final readonly class VerificationOutcome
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public VerificationStatus $status,
        public ?string $providerRef = null,
        public array $evidence = [],
        public ?string $failureReason = null,
        public ?Carbon $expiresAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public static function verified(array $evidence = [], ?string $ref = null, ?Carbon $expiresAt = null): self
    {
        return new self(VerificationStatus::Verified, $ref, $evidence, null, $expiresAt);
    }

    public static function pending(?string $ref = null): self
    {
        return new self(VerificationStatus::Pending, $ref);
    }

    public static function failed(string $reason, ?string $ref = null): self
    {
        return new self(VerificationStatus::Failed, $ref, [], $reason);
    }
}
