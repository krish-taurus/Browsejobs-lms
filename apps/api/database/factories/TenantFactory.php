<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'domain' => null,
            'branding' => [],
            'feature_flags' => [],
            'plan' => 'standard',
            'status' => 'active',
            'batch_numbering_pattern' => '{COURSE}-{YYYYMM}-{seq}',
        ];
    }

    /**
     * Reachable at the given host (for domain-based resolution).
     */
    public function domain(string $domain): static
    {
        return $this->state(fn (array $attributes): array => [
            'domain' => $domain,
        ]);
    }

    /**
     * A suspended tenant (resolution middleware must reject it).
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'suspended',
        ]);
    }
}
