<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Cv\QuickAtsCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Free public ATS quick-check (homepage lead magnet). Deterministic — no AI
 * spend — and tightly throttled at the route. Nothing is stored: the pasted
 * text is scored and discarded.
 */
final class AtsCheckController extends Controller
{
    public function __invoke(Request $request, QuickAtsCheck $check): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'min:80', 'max:20000'],
        ]);

        return response()->json(['data' => $check->run($validated['text'])]);
    }
}
