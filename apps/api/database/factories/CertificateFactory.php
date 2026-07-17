<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'code' => Str::lower(Str::random(40)),
            'number' => 'CERT-'.fake()->unique()->numberBetween(1000, 999999),
            'title' => 'Certificate of Completion',
            'issued_at' => now(),
            'status' => Certificate::STATUS_PENDING,
        ];
    }

    public function rendered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Certificate::STATUS_RENDERED,
            'storage_path' => 'certificates/1/'.($attributes['code'] ?? Str::random(40)).'.html',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => ['revoked_at' => now()]);
    }
}
