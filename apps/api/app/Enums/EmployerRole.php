<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Workspace-scoped employer roles (PRD-E §2).
 *
 * Deliberately NOT platform Role rows: platform roles are global staff
 * concepts, while an employer role only exists inside one workspace
 * membership (a user may be owner of one workspace and hiring manager
 * in another). See ADR 0051.
 */
enum EmployerRole: string
{
    case Owner = 'owner';
    case Recruiter = 'recruiter';
    case HiringManager = 'hiring_manager';

    /** Roles allowed to manage members, credits, and workspace settings. */
    public function managesWorkspace(): bool
    {
        return $this === self::Owner;
    }

    /** Roles allowed to create/edit JDs and drive pipelines. */
    public function managesPipeline(): bool
    {
        return in_array($this, [self::Owner, self::Recruiter], true);
    }
}
