<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HiringPartnerFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HiringPartnerFeedback>
 */
class HiringPartnerFeedbackFactory extends Factory
{
    protected $model = HiringPartnerFeedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company' => fake()->company(),
            'role_title' => 'Data Engineer',
            'token' => HiringPartnerFeedback::newToken(),
            'status' => HiringPartnerFeedback::STATUS_REQUESTED,
        ];
    }
}
