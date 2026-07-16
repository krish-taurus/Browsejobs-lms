<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin message delivery log (PRD §6.9). Gated by `can:manage-messaging`.
 */
final class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = Message::query()
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->string('direction')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($w) => $w->where('recipient', 'like', $term)->orWhere('body', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate(30);

        return MessageResource::collection($messages)->response();
    }
}
