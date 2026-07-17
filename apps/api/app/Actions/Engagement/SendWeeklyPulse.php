<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Enums\MessageCategory;
use App\Models\PulseDigest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Engagement\ActiveStudents;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Weekly Market Pulse over WhatsApp/email (PRD §6.19) — MARKETING category,
 * so the Messenger guard enforces explicit opt-in, quiet hours, and frequency
 * caps per recipient. Students without opt-in are suppressed, never sent.
 */
final readonly class SendWeeklyPulse
{
    public function __construct(private Messenger $messenger) {}

    /**
     * @return array{tenants: int, attempted: int}
     */
    public function handle(): array
    {
        $tenants = Tenant::query()->where('status', 'active')->get();
        $attempted = 0;

        foreach ($tenants as $tenant) {
            $digest = PulseDigest::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('digest_date')
                ->first();

            if ($digest === null) {
                continue;
            }

            $attempted += app(TenantContext::class)->run($tenant, function () use ($tenant, $digest): int {
                $count = 0;
                $userIds = ActiveStudents::idsFor((int) $tenant->id, 500);

                foreach (User::query()->whereIn('id', $userIds)->get() as $student) {
                    $this->messenger->send($student, 'market_pulse_weekly', [
                        'name' => $student->name,
                        'body' => Str::limit($digest->narrative, 500),
                    ], ['category' => MessageCategory::Marketing]);
                    $count++;
                }

                return $count;
            });
        }

        return ['tenants' => $tenants->count(), 'attempted' => $attempted];
    }
}
