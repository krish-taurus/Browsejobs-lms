<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\InAppNotificationResource;
use App\Models\InAppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student in-app notification inbox (PRD §6.9). Scoped to the signed-in user.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = $this->visibleScope(InAppNotification::query()->where('user_id', $userId))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $unread = $this->visibleScope(InAppNotification::query()->where('user_id', $userId))
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => InAppNotificationResource::collection($items),
            'unread' => $unread,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $query = InAppNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at');

        if ($request->filled('id')) {
            $query->where('id', $request->integer('id'));
        }

        $query->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Narrows to the configured `type` allowlist (see
     * `php artisan notifications:visible-types`) — empty/unset shows
     * everything, same as before this setting existed, so nothing else
     * that reads the notification feed changes behaviour by default.
     *
     * @param  Builder<InAppNotification>  $query
     * @return Builder<InAppNotification>
     */
    private function visibleScope(Builder $query): Builder
    {
        $types = array_filter(explode(',', (string) config('notifications.visible_types', '')));

        return $types === [] ? $query : $query->whereIn('type', $types);
    }
}
