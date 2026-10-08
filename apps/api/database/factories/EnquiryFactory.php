<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+9198'.fake()->numerify('########');
        $email = fake()->unique()->safeEmail();

        return [
            'tenant_id' => Tenant::factory(),
            'type' => Enquiry::TYPE_EMPLOYER,
            'status' => 'new',
            'name' => fake()->name(),
            'email' => $email,
            'email_normalized' => strtolower($email),
            'phone' => $phone,
            'phone_normalized' => preg_replace('/\D+/', '', $phone),
            'company' => fake()->company(),
            'company_size' => '11-50',
            'roles' => '2 backend engineers',
            'city' => 'Bengaluru',
            'timeline' => 'this-month',
            'message' => null,
            'consented_at' => now(),
            'consent_version' => 'v1',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'referrer' => null,
            'landing_page' => '/employers/enquire',
            'user_agent' => 'Factory',
            'ip_hash' => hash('sha256', '127.0.0.1|factory'),
            'notified_at' => null,
        ];
    }

    public function course(): static
    {
        return $this->state(fn () => [
            'type' => Enquiry::TYPE_COURSE,
            'company' => null,
            'company_size' => null,
            'roles' => null,
            'timeline' => null,
            'course_slug' => 'data-engineering',
            'learner_status' => 'working',
            'preferred_time' => 'evening',
            'city' => 'Pune',
            'landing_page' => '/courses/enquire',
        ]);
    }
}
