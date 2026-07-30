<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmployerMemberInvited;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Queued side effect for PRD-E F1 — emails the invite link. Idempotence:
 * re-delivery of the same invite email is harmless (link is unchanged
 * and single-use at accept time).
 */
final class SendEmployerInviteEmail implements ShouldQueue
{
    public function handle(EmployerMemberInvited $event): void
    {
        $invite = $event->invite;

        app(TenantContext::class)->run($invite->tenant, function () use ($invite): void {
            $workspace = $invite->workspace()->withoutGlobalScopes()->first();
            $acceptUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/')
                .'/employer/invites/'.$invite->token;

            Mail::raw(
                "You have been invited to join {$workspace?->name} on BrowseJobs as {$invite->role->value}.\n\n"
                ."Accept your invite: {$acceptUrl}\n\n"
                .'This link is single-use and expires on '.$invite->expires_at->toDayDateTimeString().'.',
                function ($message) use ($invite, $workspace): void {
                    $message->to($invite->email)
                        ->subject('Invitation to join '.($workspace?->name ?? 'an employer workspace').' on BrowseJobs');
                },
            );
        });
    }
}
