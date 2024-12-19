<?php

declare(strict_types=1);

namespace Database\Factories\CRM\Customer;

use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Subscription;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 *
 * @method Subscription create(array $attributes = [])
 * @method Subscription make(array $attributes = [])
 * @method Subscription withoutRelationships()
 * @method Subscription|Collection<int, Subscription> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class SubscriptionFactory extends Factory
{
    use WithoutRelationships;
    use WithRelationships;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'is_active' => fake()->boolean(),
            'account_id' => Account::factory(),
            'next_service_date' => fake()->dateTimeThisYear(),
            'last_service_date' => fake()->dateTimeThisYear(),
            'agreement_length_months' => fake()->boolean() ? 12 : 24,
            'recurring_charge' => fake()->randomNumber(3),
        ];
    }
}
