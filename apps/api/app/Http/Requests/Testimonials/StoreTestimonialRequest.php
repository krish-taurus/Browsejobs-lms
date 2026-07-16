<?php

declare(strict_types=1);

namespace App\Http\Requests\Testimonials;

use Illuminate\Foundation\Http\FormRequest;

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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'consent_publish' => ['required', 'boolean'],
            'video' => ['nullable', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
        ];
    }
}
