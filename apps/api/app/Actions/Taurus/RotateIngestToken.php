<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\TaurusWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Str;

/**
 * Issues a new bot ingest token for one workspace (ADR 0052). The token is
 * what tells ingest which workspace a report belongs to, so only its sha256
 * is stored; the old token stops working at once. The raw token is returned
 * to the caller exactly once.
 */
final class RotateIngestToken
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(TaurusWorkspace $workspace, User $actor): string
    {
        $token = 'tau_'.Str::random(40);

        $workspace->forceFill([
            'ingest_token_hash' => self::hash($token),
            'ingest_token_last4' => mb_substr($token, -4),
        ])->save();

        $this->audit->log('taurus.ingest_token.rotated', $workspace, [], $actor);

        return $token;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
