<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Staff view of an enquiry. The IP hash stays out of JSON; the CSV export has it.
 *
 * @mixin Enquiry
 */
final class EnquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'company_size' => $this->company_size,
            'roles' => $this->roles,
            'city' => $this->city,
            'timeline' => $this->timeline,
            'course_slug' => $this->course_slug,
            'learner_status' => $this->learner_status,
            'preferred_time' => $this->preferred_time,
            'message' => $this->message,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'referrer' => $this->referrer,
            'landing_page' => $this->landing_page,
            'consented_at' => $this->consented_at?->toIso8601String(),
            'notified_at' => $this->notified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
