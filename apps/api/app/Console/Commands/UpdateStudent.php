<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Funnel\SendBatchCredentials;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Edits a student's profile from the CRM: contact details, active flag, and
 * --send-login re-sends the sign-in instructions (batch number + OTP login,
 * no passwords) for their latest occupying membership.
 */
final class UpdateStudent extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'student:update
        {user : Student user id, email or phone}
        {--name= : New display name}
        {--email= : New email}
        {--phone= : New phone}
        {--active= : 1 to activate, 0 to deactivate the account}
        {--send-login : Re-send the OTP sign-in instructions (WhatsApp + email)}';

    protected $description = "Update a student's profile, activate/deactivate them, or resend login instructions";

    public function handle(SendBatchCredentials $credentials): int
    {
        $student = $this->findStudent((string) $this->argument('user'));

        if ($student === null) {
            $this->error("Student '{$this->argument('user')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($student->tenant_id, function () use ($student, $credentials): int {
            $updates = [];

            if ($this->option('name')) {
                $updates['name'] = $this->option('name');
            }
            if ($this->option('email') !== null) {
                $updates['email'] = $this->option('email') ?: null;
            }
            if ($this->option('phone') !== null) {
                $updates['phone'] = $this->option('phone') ?: null;
            }
            if (in_array($this->option('active'), ['0', '1'], true)) {
                $updates['is_active'] = $this->option('active') === '1';
            }

            if ($updates !== []) {
                $student->update($updates);
                $this->info("Student #{$student->id} updated: ".implode(', ', array_keys($updates)).'.');
            }

            if ($this->option('send-login')) {
                $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
                $latestMembership = BatchMember::query()->withoutGlobalScope(TenantScope::class)
                    ->where('user_id', $student->id)
                    ->whereIn('status', $occupying)
                    ->with('batch')
                    ->latest('id')
                    ->first();

                if ($latestMembership?->batch !== null) {
                    $credentials->handle($student->fresh(), $latestMembership->batch);
                    $this->info('OTP sign-in instructions sent on WhatsApp + email.');
                } else {
                    $this->warn('Student has no occupying batch — nothing to send.');
                }
            }

            if ($updates === [] && ! $this->option('send-login')) {
                $this->warn('Nothing to update.');
            }

            return self::SUCCESS;
        });
    }
}
