<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Models\ContentHubItem;
use App\Models\InAppNotification;
use App\Models\User;
use App\Support\Engagement\ActiveStudents;
use App\Support\Tenancy\TenantContext;

/**
 * Adds a Content Hub release and pushes it in-app to active students
 * (PRD §6.19: "push notification per release"). Promotional WhatsApp is never
 * sent per release — only the weekly opt-in digest.
 */
final readonly class PublishContentItem
{
    /**
     * @param  array{kind: string, title: string, url: string, published_at?: string|null}  $attributes
     */
    public function handle(array $attributes, ?User $creator = null): ContentHubItem
    {
        $tenantId = app(TenantContext::class)->id();

        $item = ContentHubItem::query()->create([
            'tenant_id' => $tenantId,
            'kind' => $attributes['kind'],
            'title' => $attributes['title'],
            'url' => $attributes['url'],
            'source' => 'manual',
            'published_at' => $attributes['published_at'] ?? now(),
            'created_by' => $creator?->id,
        ]);

        $label = match ($item->kind) {
            'youtube' => 'New video',
            'podcast' => 'New episode',
            default => 'New post',
        };

        ActiveStudents::idsFor((int) $tenantId, (int) config('engagement.content_fanout', 200))
            ->each(fn (int $userId) => InAppNotification::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'title' => "{$label}: {$item->title}",
                'body' => 'Fresh on the BrowseJobs Content Hub.',
                'url' => '/pulse',
            ]));

        return $item;
    }
}
