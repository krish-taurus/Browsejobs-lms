<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\InAppNotificationResource;
use App\Models\InAppNotification;
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

        $items = InAppNotification::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $unread = InAppNotification::query()->where('user_id', $userId)->whereNull('read_at')->count();

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
}
