<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\ActivityEvent;
use App\Models\BatchMember;
use App\Models\InAppNotification;
use App\Models\InterventionAlert;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Rage-signal detection (PRD §6.20): deterministic churn precursors —
 * repeated failed payments, repeated low-CSAT tickets, and engagement
 * cliffs — open an intervention alert and page the counselors BEFORE the
 * frustration goes public. One alert per student per cooldown window.
 * (Sentiment analysis over inbound messages is a later addition.)
 */
final class RunRageSignals extends Command
{
    protected $signature = 'care:signals';

    protected $description = 'Detect churn precursors and open intervention alerts';

    public function handle(): int
    {
        $opened = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$opened): void {
            app(TenantContext::class)->run($tenant, function () use ($tenant, &$opened): void {
                $studentIds = BatchMember::query()
                    ->whereIn('status', ['enrolled', 'reserved', 'payment_pending'])
                    ->pluck('user_id')
                    ->unique();

                foreach (User::query()->whereIn('id', $studentIds)->get() as $student) {
                    $signals = $this->signalsFor($student);
                    if ($signals === []) {
                        continue;
                    }

                    $recent = InterventionAlert::query()
                        ->where('user_id', $student->id)
                        ->where('created_at', '>=', now()->subDays((int) config('retention.rage.cooldown_days', 14)))
                        ->exists();

                    if ($recent) {
                        continue;
                    }

                    InterventionAlert::query()->create([
                        'tenant_id' => $tenant->id,
                        'user_id' => $student->id,
                        'signals' => $signals,
                    ]);
                    $opened++;

                    foreach ($this->counselors($tenant) as $counselor) {
                        InAppNotification::query()->create([
                            'tenant_id' => $tenant->id,
                            'user_id' => $counselor->id,
                            'title' => "Priority intervention: {$student->name}",
                            'body' => 'Churn signals detected: '.implode(', ', $signals).'. Reach out today.',
                            'url' => '/admin/care',
                        ]);
                    }
                }
            });
        });

        $this->info("Opened {$opened} intervention alert(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function signalsFor(User $student): array
    {
        $signals = [];
        $window = now()->subDays((int) config('retention.rage.window_days', 30));

        $failedPayments = Payment::query()
            ->where('status', PaymentStatus::Failed->value)
            ->where('created_at', '>=', $window)
            ->whereHas('feePlan', fn ($q) => $q->where('user_id', $student->id))
            ->count();
        if ($failedPayments >= (int) config('retention.rage.failed_payments', 2)) {
            $signals[] = 'repeated failed payments';
        }

        $lowCsat = Ticket::query()
            ->where('student_id', $student->id)
            ->where('csat_rating', '<=', 2)
            ->where('csat_at', '>=', now()->subDays(90))
            ->count();
        if ($lowCsat >= (int) config('retention.rage.low_csat_tickets', 2)) {
            $signals[] = 'repeated low CSAT';
        }

        $silentDays = (int) config('retention.rage.silent_days', 7);
        $wasActive = ActivityEvent::query()
            ->where('user_id', $student->id)
            ->whereBetween('occurred_at', [$window, now()->subDays($silentDays)])
            ->exists();
        $nowSilent = ! ActivityEvent::query()
            ->where('user_id', $student->id)
            ->where('occurred_at', '>=', now()->subDays($silentDays))
            ->exists();
        if ($wasActive && $nowSilent) {
            $signals[] = 'engagement cliff';
        }

        return $signals;
    }

    /**
     * @return Collection<int, User>
     */
    private function counselors(Tenant $tenant)
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_type', 'staff')
            ->whereHas('roles', fn ($q) => $q->where('slug', 'counselor'))
            ->get();
    }
}
