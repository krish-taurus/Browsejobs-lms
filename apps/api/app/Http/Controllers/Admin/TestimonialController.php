<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Testimonials\ApproveTestimonial;
use App\Actions\Testimonials\RejectTestimonial;
use App\Http\Controllers\Controller;
use App\Http\Requests\Testimonials\RejectTestimonialRequest;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin testimonial moderation (PRD §5 Stage 3). Gated by `can:manage-vouchers`
 * (approval issues a voucher). Approve publishes to the /reviews wall + issues
 * the reward; reject does neither.
 */
final class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString() ?: 'pending';

        $items = Testimonial::query()
            ->when(
                in_array($status, ['pending', 'approved', 'rejected', 'private_feedback'], true),
                fn ($q) => $q->where('status', $status),
            )
            ->with(['student:id,name,phone,email', 'batch:id,number', 'voucherIssue'])
            ->orderByDesc('id')
            ->paginate(20);

        return TestimonialResource::collection($items)->response();
    }

    public function approve(Testimonial $testimonial, ApproveTestimonial $approve, Request $request): JsonResponse
    {
        $result = $approve->handle($testimonial, $request->user());
        $result->load(['student:id,name,phone,email', 'batch:id,number', 'voucherIssue']);

        return (new TestimonialResource($result))->response();
    }

    public function reject(RejectTestimonialRequest $request, Testimonial $testimonial, RejectTestimonial $reject): JsonResponse
    {
        $result = $reject->handle($testimonial, $request->string('reason')->toString(), $request->user());
        $result->load(['student:id,name,phone,email', 'batch:id,number']);

        return (new TestimonialResource($result))->response();
    }
}
