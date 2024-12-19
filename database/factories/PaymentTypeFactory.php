<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentType;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentType>
 */
class PaymentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->randomElement(PaymentTypeEnum::cases())->value,
            'name' => $this->faker->word,
            'description' => $this->faker->text,
            'is_hidden' => $this->faker->boolean,
            'is_enabled' => $this->faker->boolean,
            'sort_order' => $this->faker->unique()->randomNumber(nbDigits: 4),
        ];
    }
}
