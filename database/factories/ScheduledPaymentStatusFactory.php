<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScheduledPaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledPaymentStatusFactory extends Factory
{
    protected $model = ScheduledPaymentStatus::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
        ];
    }
}
