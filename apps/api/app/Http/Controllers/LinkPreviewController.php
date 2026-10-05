<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Chat\FetchLinkPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Rich preview for a URL a student sees pasted in batch chat (Sept 2026) —
 * one small endpoint, not baked into the message list, so the same link
 * shared across many messages is fetched once and the chat itself never
 * waits on an external site to respond.
 */
final class LinkPreviewController extends Controller
{
    public function show(Request $request, FetchLinkPreview $fetch): JsonResponse
    {
        $url = (string) $request->query('url', '');

        if ($url === '' || mb_strlen($url) > 2000) {
            throw ValidationException::withMessages(['url' => 'A valid URL is required.']);
        }

        return response()->json(['data' => $fetch->handle($url)]);
    }
}
