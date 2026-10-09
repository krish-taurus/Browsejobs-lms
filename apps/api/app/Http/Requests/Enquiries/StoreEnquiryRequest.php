<?php

declare(strict_types=1);

namespace App\Http\Requests\Enquiries;

use App\Models\Enquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Enquiry::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^\+?[0-9][0-9\s\-()]{6,18}$/'],
            'company' => ['required_if:type,employer', 'nullable', 'string', 'max:160'],
            'company_size' => ['required_if:type,employer', 'nullable', 'string', Rule::in(array_keys(Enquiry::COMPANY_SIZES))],
            'roles' => ['required_if:type,employer', 'nullable', 'string', 'max:240'],
            'timeline' => ['required_if:type,employer', 'nullable', 'string', Rule::in(array_keys(Enquiry::TIMELINES))],
            'course_slug' => ['required_if:type,course', 'nullable', 'string', Rule::in(config('enquiry.courses'))],
            'learner_status' => ['required_if:type,course', 'nullable', 'string', Rule::in(array_keys(Enquiry::LEARNER_STATUSES))],
            'preferred_time' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), [Enquiry::TYPE_COURSE, Enquiry::TYPE_COUNSELLING], true)),
                'nullable',
                'string',
                Rule::in(array_keys(Enquiry::PREFERRED_TIMES)),
            ],
            'city' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'landing_page' => ['nullable', 'string', 'max:500'],
            'website' => ['prohibited'],
            'form_started_at' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'Please confirm we may contact you about this enquiry.',
            'website.prohibited' => 'This submission could not be accepted.',
            'course_slug.in' => 'Choose a course that is open now.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $started = (int) $this->input('form_started_at');
            $now = (int) round(microtime(true) * 1000);
            $elapsed = $now - $started;

            if ($elapsed < 3000) {
                $validator->errors()->add('form', 'Please wait a moment and try again.');
            }

            if ($elapsed > 86_400_000) {
                $validator->errors()->add('form', 'This form expired. Refresh the page and try again.');
            }
        });
    }
}
