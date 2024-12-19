<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScheduledPaymentTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledPaymentTriggerFactory extends Factory
{
    protected $model = ScheduledPaymentTrigger::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(120),
        ];
    }
}
