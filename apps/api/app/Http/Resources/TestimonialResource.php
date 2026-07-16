<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin Testimonial
 */
final class TestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'course_slug' => $this->course_slug,
            'status' => $this->status->value,
            'consent_publish' => $this->consent_publish,
            'has_video' => $this->video_path !== null,
            'video_url' => $this->videoUrl(),
            'reject_reason' => $this->reject_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'student' => $this->whenLoaded('student', fn () => $this->student === null ? null : [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'phone' => $this->student->phone,
                'email' => $this->student->email,
            ]),
            'batch' => $this->whenLoaded('batch', fn () => $this->batch === null ? null : [
                'id' => $this->batch->id,
                'number' => $this->batch->number,
            ]),
            'voucher_issue' => $this->whenLoaded('voucherIssue', fn () => $this->voucherIssue === null ? null : [
                'code' => $this->voucherIssue->code,
                'status' => $this->voucherIssue->status->value,
            ]),
        ];
    }

    /**
     * A short-lived signed URL for the uploaded video, or null when unavailable
     * (no video, or the disk driver cannot mint temporary URLs).
     */
    private function videoUrl(): ?string
    {
        if ($this->video_path === null) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($this->video_path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }
}
