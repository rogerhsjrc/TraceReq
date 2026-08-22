<?php

namespace Database\Factories;

use App\Models\TraceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TraceRequest> */
class TraceRequestFactory extends Factory
{
    protected $model = TraceRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'requested_amount' => fake()->randomElement([
                '10.0000',
                '125.5000',
                '2500.7500',
            ]),
            'currency_code' => fake()->randomElement(['ARS', 'EUR', 'USD']),
        ];
    }
}
