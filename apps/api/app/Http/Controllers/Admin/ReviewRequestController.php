<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Reviews\SendManualReviewRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\SendReviewRequestsRequest;
use Illuminate\Http\JsonResponse;

/**
 * Manual review requests (Platform Spec §3): send the review link by hand to a
 * batch, a set of students, or both. Gated `can:manage-vouchers` (the same gate
 * as testimonial moderation, since a review can earn a voucher).
 */
final class ReviewRequestController extends Controller
{
    public function store(SendReviewRequestsRequest $request, SendManualReviewRequests $send): JsonResponse
    {
        $summary = $send->handle($request->user(), [
            'batch_id' => $request->integer('batch_id') ?: null,
            'user_ids' => array_map('intval', (array) $request->input('user_ids', [])),
            'stage' => $request->input('stage'),
            'force' => $request->has('force') ? $request->boolean('force') : true,
        ]);

        return response()->json(['data' => $summary]);
    }
}
