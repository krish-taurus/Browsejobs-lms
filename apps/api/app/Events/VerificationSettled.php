<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CandidateVerificationCheck;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A verification check reached a terminal state (PRD-E F9). Fired for both
 * outcomes — a candidate who submitted something is owed an answer either
 * way, and a silent failure is the state that wastes their time.
 */
final class VerificationSettled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly CandidateVerificationCheck $check) {}
}
