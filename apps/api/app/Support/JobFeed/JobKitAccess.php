<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Enums\BatchMemberStatus;
use App\Enums\EntitlementFeature;
use App\Models\BatchMember;
use App\Models\JobFeedItem;
use App\Models\JobKitUnlock;
use App\Models\Product;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;

/**
 * Who gets the full Interview Kit for a posting (ADR 0048):
 * - enrolled students (any occupying batch seat) — it's part of the program;
 * - Career+ subscribers — the unlimited tier;
 * - anyone who spent a `job_kit` credit on that specific posting.
 * Everyone else sees the free sample and the offer. Lookups are cached per
 * instance so a 40-item feed costs three queries, not 120.
 */
final class JobKitAccess
{
    private ?bool $allAccess = null;

    /** @var list<int>|null */
    private ?array $unlockedItemIds = null;

    public function __construct(
        private readonly User $user,
        private readonly EntitlementService $entitlements,
    ) {}

    public function unlockedFor(JobFeedItem $item): bool
    {
        return $this->hasAllAccess() || in_array($item->id, $this->unlockedIds(), true);
    }

    /** Spend one job_kit credit to unlock this posting. Idempotent per posting. */
    public function unlock(JobFeedItem $item): void
    {
        if ($this->unlockedFor($item)) {
            return;
        }

        // Throws a ValidationException mentioning credits when the wallet is
        // empty — the controller answers 402 with the kit offers.
        $this->entitlements->consumeCredits($this->user, EntitlementFeature::JobKit->value, 1, "job_kit:{$item->id}");

        JobKitUnlock::query()->firstOrCreate([
            'user_id' => $this->user->id,
            'job_feed_item_id' => $item->id,
        ], ['tenant_id' => $this->user->tenant_id]);

        $this->unlockedItemIds = null; // refresh on next read
    }

    public function creditBalance(): int
    {
        return $this->entitlements->balance($this->user, EntitlementFeature::JobKit->value);
    }

    /**
     * The kit offers, server-owned prices only (CLAUDE.md: figures are never
     * client-sent).
     *
     * @return list<array{product_id: int, sku: string, name: string, price_paise: int}>
     */
    public function offers(): array
    {
        return Product::query()
            ->where('feature', EntitlementFeature::JobKit->value)
            ->where('active', true)
            ->orderBy('price_paise')
            ->get(['id', 'sku', 'name', 'price_paise'])
            ->map(fn (Product $p) => [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'price_paise' => $p->price_paise,
            ])
            ->all();
    }

    private function hasAllAccess(): bool
    {
        if ($this->allAccess !== null) {
            return $this->allAccess;
        }

        $enrolled = BatchMember::query()
            ->where('user_id', $this->user->id)
            ->whereIn('status', array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying()))
            ->exists();

        return $this->allAccess = $enrolled || $this->entitlements->hasEntitlement($this->user, 'career_plus');
    }

    /**
     * @return list<int>
     */
    private function unlockedIds(): array
    {
        return $this->unlockedItemIds ??= JobKitUnlock::query()
            ->where('user_id', $this->user->id)
            ->pluck('job_feed_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
