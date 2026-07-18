<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DataRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataRequest>
 */
class DataRequestFactory extends Factory
{
    protected $model = DataRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => DataRequest::TYPE_ACCESS,
            'status' => DataRequest::STATUS_PENDING,
        ];
    }
}
