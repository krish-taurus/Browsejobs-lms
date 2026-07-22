<?php

declare(strict_types=1);

namespace App\Http\Controllers\Testimonials;

use App\Actions\Testimonials\SubmitTestimonial;
use App\Http\Controllers\Controller;
use App\Http\Requests\Testimonials\StoreTestimonialRequest;
use App\Http\Resources\TestimonialResource;
use Illuminate\Http\JsonResponse;

/**
 * Student-facing testimonial submission (PRD §5 Stage 3). Reached via the magic
 * link sent when a bootcamp completes; the student is already authenticated.
 */
final class TestimonialController extends Controller
{
    public function store(StoreTestimonialRequest $request, SubmitTestimonial $submit): JsonResponse
    {
        $testimonial = $submit->handle(
            $request->user(),
            [
                'batch_id' => $request->integer('batch_id') ?: null,
                'course_slug' => $request->input('course_slug'),
                'stage' => $request->input('stage'),
                'rating' => $request->integer('rating'),
                'body' => $request->string('body')->toString(),
                'consent_publish' => $request->boolean('consent_publish'),
                'followed_instagram' => $request->boolean('followed_instagram'),
                'subscribed_youtube' => $request->boolean('subscribed_youtube'),
                'posted_google_review' => $request->boolean('posted_google_review'),
            ],
            $request->file('video'),
        );

        return (new TestimonialResource($testimonial))->response()->setStatusCode(201);
    }
}
