<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OldPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OldPayment>
 */
class OldPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => $this->faker->randomDigitNot(0),
            'ticket_id' => $this->faker->randomDigitNot(0),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'success' => $this->faker->boolean(),
        ];
    }
}
