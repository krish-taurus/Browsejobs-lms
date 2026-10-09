<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A member's role inside one Taurus workspace (ADR 0052). Membership-scoped,
 * like employer roles — not a platform Role row.
 */
enum TaurusWorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Viewer = 'viewer';

    /** Owners and admins manage keys, tokens and approvals; viewers only watch and ask. */
    public function manages(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::cases());
    }
}
