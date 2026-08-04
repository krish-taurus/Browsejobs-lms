<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Funnel\CompleteEndedBootcamps;
use App\Actions\Funnel\GenerateWeeklyMasterclasses;
use App\Actions\Funnel\RolloverMasterclassesToBootcamps;
use App\Actions\Funnel\SendBootcampPaymentNudges;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * The automated enrolment funnel (PRD §5 funnel spine), one daily sweep:
 *  1. build this Saturday's masterclass batches from course-interest leads
 *     and enrol every registered student who booked one,
 *  2. roll finished masterclasses into a fresh 7-day bootcamp (with its
 *     linked paid batch created up front),
 *  3. complete ended bootcamps, converting attendees into the paid batch.
 * Every step is idempotent, so a missed day self-heals on the next run.
 */
final class AdvanceEnrolmentFunnel extends Command
{
    protected $signature = 'funnel:advance';

    protected $description = 'Advance the masterclass → bootcamp → paid enrolment funnel for every tenant';

    public function handle(
        GenerateWeeklyMasterclasses $generateMasterclasses,
        RolloverMasterclassesToBootcamps $rollover,
        CompleteEndedBootcamps $completeBootcamps,
        SendBootcampPaymentNudges $paymentNudges,
        TenantContext $tenants,
    ): int {
        $autoAdvance = (bool) config('funnel.auto_advance');

        if (! $autoAdvance) {
            $this->warn('Automatic masterclass/batch creation is OFF (FUNNEL_AUTO_ADVANCE=false) — batches are created and students allocated manually from the CRM. Running only the day-5 payment nudges.');
        }

        Tenant::query()->get()->each(function (Tenant $tenant) use ($autoAdvance, $generateMasterclasses, $rollover, $completeBootcamps, $paymentNudges, $tenants): void {
            $tenants->run($tenant, function () use ($tenant, $autoAdvance, $generateMasterclasses, $rollover, $completeBootcamps, $paymentNudges): void {
                if (! $autoAdvance) {
                    $nudged = $paymentNudges->handle();
                    $this->info(sprintf('[%s] day-5 payment nudges: %d', $tenant->slug ?? $tenant->id, $nudged));

                    return;
                }

                $mc = $generateMasterclasses->handle();
                $roll = $rollover->handle();
                $done = $completeBootcamps->handle();
                $nudged = $paymentNudges->handle();

                $this->info(sprintf(
                    '[%s] masterclasses: +%d batches / %d enrolled · rollovers: %d (%d into bootcamp) · bootcamps completed: %d (%d converted, %d awaiting paid link) · day-5 payment nudges: %d',
                    $tenant->slug ?? $tenant->id,
                    $mc['batches'], $mc['enrolled'],
                    $roll['rolled'], $roll['enrolled'],
                    $done['completed'], $done['converted'], $done['skipped'],
                    $nudged,
                ));
            });
        });

        return self::SUCCESS;
    }
}
