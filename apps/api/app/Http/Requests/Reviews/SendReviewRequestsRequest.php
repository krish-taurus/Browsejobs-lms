<?php

declare(strict_types=1);

namespace App\Http\Requests\Reviews;

use App\Enums\ReviewStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SendReviewRequestsRequest extends FormRequest
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
            'batch_id' => ['nullable', 'integer'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'stage' => ['nullable', 'string', Rule::in(ReviewStage::values())],
            'force' => ['nullable', 'boolean'],
        ];
    }

    /** At least one target is required — a batch or some students. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('batch_id') && ! $this->filled('user_ids')) {
                $validator->errors()->add('target', 'Pick a batch or at least one student to send to.');
            }
        });
    }
}
