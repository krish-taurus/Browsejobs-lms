<?php

declare(strict_types=1);

namespace App\Http\Requests\Testimonials;

use Illuminate\Foundation\Http\FormRequest;

final class RejectTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware enforces can:manage-vouchers.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
