<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentType;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentType>
 */
class DeclineReasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->randomElement(DeclineReasonEnum::cases())->value,
            'name' => $this->faker->word,
            'description' => $this->faker->text,
            'is_reprocessable' => $this->faker->boolean,
        ];
    }
}
