<?php

declare(strict_types=1);

namespace Database\Factories\CRM\Dealer;

use App\Models\CRM\Organization\Dealer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dealer>
 */
class DealerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Dealer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name,
        ];
    }
}
