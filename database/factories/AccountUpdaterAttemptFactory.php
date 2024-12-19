<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountUpdaterAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUpdaterAttempt>
 */
class AccountUpdaterAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'requested_at' => $this->faker->dateTimeBetween(startDate: '-10 days'),
        ];
    }
}
