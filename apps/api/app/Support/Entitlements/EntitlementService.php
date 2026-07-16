<?php

declare(strict_types=1);

namespace App\Support\Entitlements;

use App\Enums\EnrolmentType;
use App\Enums\EntitlementFeature;
use App\Enums\EntitlementStatus;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Entitlement;
use App\Models\MonetizationSetting;
use App\Models\ProductPurchase;
use App\Models\QuotaUsage;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The central Entitlement Service (PRD §6.17) — the whitelabel monetization
 * backbone. Governs countable credit wallets, non-countable access entitlements,
 * and period quota usage. Phase-4 metered features call `consumeCredits`.
 * Every mutation runs inside the user's tenant context (safe from unscoped
 * me/webhook callers).
 */
final class EntitlementService
{
    public function grantCredits(User $user, string $feature, int $amount, string $reason, ?Model $source = null): CreditWallet
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $feature, $amount, $reason, $source): CreditWallet {
            $wallet = $this->wallet($user, $feature);
            $wallet->increment('balance', $amount);
            $wallet->refresh();
            $this->record($user, $feature, $amount, $wallet->balance, $reason, $source);

            return $wallet;
        });
    }

    public function consumeCredits(User $user, string $feature, int $amount, string $reason): CreditWallet
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $feature, $amount, $reason): CreditWallet {
            $wallet = $this->wallet($user, $feature);

            if ($wallet->balance < $amount) {
                throw ValidationException::withMessages(['feature' => "Not enough {$feature} credits."]);
            }

            $wallet->decrement('balance', $amount);
            $wallet->refresh();
            $this->record($user, $feature, -$amount, $wallet->balance, $reason);
            $this->recordUsage($user, $feature, $amount, 'all-time');

            return $wallet;
        });
    }

    /** Deduct up to $amount credits without going below zero (refund claw-back). */
    public function clawbackCredits(User $user, string $feature, int $amount, string $reason): CreditWallet
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $feature, $amount, $reason): CreditWallet {
            $wallet = $this->wallet($user, $feature);
            $take = min($wallet->balance, $amount);

            if ($take > 0) {
                $wallet->decrement('balance', $take);
                $wallet->refresh();
                $this->record($user, $feature, -$take, $wallet->balance, $reason);
            }

            return $wallet;
        });
    }

    public function balance(User $user, string $feature): int
    {
        return (int) (CreditWallet::query()->withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('feature', $feature)
            ->value('balance') ?? 0);
    }

    public function grantEntitlement(User $user, string $key, string $kind, ?Carbon $expiresAt = null, ?ProductPurchase $source = null): Entitlement
    {
        return app(TenantContext::class)->run($user->tenant, fn (): Entitlement => Entitlement::query()->updateOrCreate(
            ['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'key' => $key],
            [
                'kind' => $kind,
                'status' => EntitlementStatus::Active->value,
                'source_purchase_id' => $source?->id,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
            ],
        ));
    }

    public function revokeEntitlement(User $user, string $key): void
    {
        app(TenantContext::class)->run($user->tenant, function () use ($user, $key): void {
            Entitlement::query()->where('user_id', $user->id)->where('key', $key)
                ->update(['status' => EntitlementStatus::Revoked->value]);
        });
    }

    public function hasEntitlement(User $user, string $key): bool
    {
        return Entitlement::query()->withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->where('status', EntitlementStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function recordUsage(User $user, string $feature, int $amount, string $periodKey): QuotaUsage
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $feature, $amount, $periodKey): QuotaUsage {
            $usage = QuotaUsage::query()->firstOrCreate(
                ['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'feature' => $feature, 'period_key' => $periodKey],
                ['used' => 0],
            );
            $usage->increment('used', $amount);

            return $usage->refresh();
        });
    }

    /** Seed a new enrolment's included quotas (PRD §6.17 freemium guardrail). */
    public function grantIncludedQuota(User $user, EnrolmentType $type): void
    {
        app(TenantContext::class)->run($user->tenant, function () use ($user, $type): void {
            $voice = $this->includedQuota(EntitlementFeature::VoiceMock->value, $type);
            if ($voice > 0) {
                $this->grantCredits($user, EntitlementFeature::VoiceMock->value, $voice, "included:{$type->value}");
            }
        });
    }

    public function includedQuota(string $feature, EnrolmentType $type): int
    {
        $settings = $this->settings();

        if ($feature === EntitlementFeature::VoiceMock->value) {
            return $type === EnrolmentType::SelfPaced
                ? $settings->voice_included_self_paced
                : $settings->voice_included_live;
        }

        return 0;
    }

    /** The tenant's monetization settings (created from config defaults on first read). */
    public function settings(): MonetizationSetting
    {
        return MonetizationSetting::query()->firstOrCreate([], [
            'cv_free_grants' => (int) config('monetization.cv.free_grants', 3),
            'voice_included_live' => (int) config('monetization.voice_mock.included_live', 5),
            'voice_included_self_paced' => (int) config('monetization.voice_mock.included_self_paced', 2),
            'self_paced_pct_bps' => (int) config('monetization.self_paced.pct_bps', 5000),
            'text_practice_enabled' => (bool) config('monetization.text_practice_enabled', false),
        ]);
    }

    private function wallet(User $user, string $feature): CreditWallet
    {
        return CreditWallet::query()->firstOrCreate(
            ['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'feature' => $feature],
            ['balance' => 0],
        );
    }

    private function record(User $user, string $feature, int $delta, int $balanceAfter, string $reason, ?Model $source = null): void
    {
        CreditTransaction::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'feature' => $feature,
            'delta' => $delta,
            'balance_after' => $balanceAfter,
            'reason' => $reason,
            'source_type' => $source !== null ? $source::class : null,
            'source_id' => $source?->getKey(),
        ]);
    }
}
