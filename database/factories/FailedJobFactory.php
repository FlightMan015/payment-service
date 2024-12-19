<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FailedJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailedJob>
 */
class FailedJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'connection' => $this->faker->word,
            'queue' => $this->faker->word,
            'payload' => $this->faker->text,
            'exception' => $this->faker->text,
            'failed_at' => $this->faker->dateTimeBetween(startDate: '-30 days', endDate: 'now'),
        ];
    }
}
