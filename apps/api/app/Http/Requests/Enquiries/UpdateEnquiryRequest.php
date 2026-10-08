<?php

declare(strict_types=1);

namespace App\Http\Requests\Enquiries;

use App\Models\Enquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-leads') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Enquiry::STATUSES)],
        ];
    }
}
