<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TestimonialStatus;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'batch_id' => null,
            'course_slug' => 'python-fullstack',
            'rating' => 5,
            'body' => 'The bootcamp was direct and honest. I finally understood what interviews actually ask.',
            'video_path' => null,
            'consent_publish' => true,
            'status' => TestimonialStatus::Pending->value,
            'review_id' => null,
            'voucher_issue_id' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'reject_reason' => null,
        ];
    }
}
