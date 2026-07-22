<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public review-gate config (Platform Spec §3) for the candidate review page:
 * the Google "write a review" link and the Instagram / YouTube URLs a 4★+
 * candidate is handed off to, plus the star threshold the gate uses. Tenant is
 * resolved by domain. No secrets — these are public destinations.
 */
final class ReviewLinksController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'min_public_rating' => (int) config('vouchers.review_request.min_public_rating', 4),
                'google_review_url' => (string) config('services.social.google_review_url', ''),
                'instagram_url' => (string) config('services.social.instagram_url', ''),
                'youtube_url' => (string) config('services.social.youtube_url', ''),
            ],
        ]);
    }
}
