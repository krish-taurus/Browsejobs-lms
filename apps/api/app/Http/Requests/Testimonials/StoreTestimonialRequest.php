<?php

declare(strict_types=1);

namespace App\Http\Requests\Testimonials;

use App\Enums\ReviewStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mimes = implode(',', (array) config('vouchers.upload.mimes', ['mp4', 'mov', 'webm']));
        $maxKb = (int) config('vouchers.upload.max_video_mb', 50) * 1024;

        return [
            'batch_id' => ['nullable', 'integer'],
            'course_slug' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string', Rule::in(ReviewStage::values())],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            // Consent + social attestations only matter for a public (4★+) review;
            // the action ignores them below the threshold, so they stay optional.
            'consent_publish' => ['nullable', 'boolean'],
            'followed_instagram' => ['nullable', 'boolean'],
            'subscribed_youtube' => ['nullable', 'boolean'],
            'posted_google_review' => ['nullable', 'boolean'],
            'video' => ['nullable', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
        ];
    }
}
