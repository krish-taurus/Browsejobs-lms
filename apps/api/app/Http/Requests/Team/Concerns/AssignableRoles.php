<?php

declare(strict_types=1);

namespace App\Http\Requests\Team\Concerns;

/**
 * The staff roles an institute admin may assign. `super-admin` is deliberately
 * excluded — that tier is provisioned at the platform level, never minted from
 * the Team screen.
 */
trait AssignableRoles
{
    /**
     * @return list<string>
     */
    protected function assignableRoles(): array
    {
        return ['admin', 'trainer', 'mentor', 'counselor', 'placement-officer', 'support-agent'];
    }
}
