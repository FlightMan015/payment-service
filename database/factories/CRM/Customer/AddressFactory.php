<?php

declare(strict_types=1);

namespace Database\Factories\CRM\Customer;

use App\Models\CRM\Customer\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 *
 * @method Address create(array $attributes = [])
 * @method Address make(array $attributes = [])
 */
class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'NY',
            'postal_code' => fake()->postcode()
        ];
    }
}
