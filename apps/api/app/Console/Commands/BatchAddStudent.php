<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Funnel\SendBatchCredentials;
use App\Actions\Roster\AddStudentToBatch;
use App\Actions\Roster\FindOrCreateStudent;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\BatchMemberStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Adds a student to a batch from the CRM: resolves (or creates) the student by
 * phone/email, seats them with the given membership status, and — with
 * --credentials — sends the batch number + OTP sign-in instructions on
 * WhatsApp + email. No passwords are ever generated or sent.
 */
final class BatchAddStudent extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'batch:add-student
        {batch : Batch id or number}
        {name : Student name}
        {--phone= : Student phone}
        {--email= : Student email}
        {--status=enrolled : Membership status (reserved|payment_pending|enrolled)}
        {--credentials : Send batch number + OTP sign-in instructions (WhatsApp + email)}';

    protected $description = 'Add (or create) a student and seat them in a batch, optionally sending login instructions';

    public function handle(
        FindOrCreateStudent $findOrCreate,
        AddStudentToBatch $addStudent,
        SendBatchCredentials $credentials,
    ): int {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        $status = BatchMemberStatus::tryFrom((string) $this->option('status'));

        if ($status === null) {
            $this->error("Unknown membership status '{$this->option('status')}'.");

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch, $status, $findOrCreate, $addStudent, $credentials): int {
            try {
                $student = $findOrCreate->handle(
                    Tenant::query()->findOrFail($batch->tenant_id),
                    (string) $this->argument('name'),
                    $this->option('email') ?: null,
                    $this->option('phone') ?: null,
                );

                // Already a member (e.g. seated earlier by the old automation or
                // another admin)? That IS the desired end state — idempotent
                // success, and the credentials below still go out if they never
                // received any.
                $alreadyMember = $batch->members()->where('user_id', $student->id)->exists();

                if ($alreadyMember) {
                    $this->info("{$student->name} (#{$student->id}) is already in batch {$batch->number} — nothing to add.");
                } else {
                    $addStudent->handle($batch, $student, $status);
                    $this->info("Added {$student->name} (#{$student->id}) to batch {$batch->number} as {$status->value}.");
                }
            } catch (ValidationException $e) {
                $this->error(collect($e->errors())->flatten()->implode(' '));

                return self::FAILURE;
            }

            if ($this->option('credentials')) {
                $credentials->handle($student, $batch);
                $this->info('Batch number + OTP sign-in instructions sent on WhatsApp + email.');
            }

            return self::SUCCESS;
        });
    }
}
