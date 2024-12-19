<?php

declare(strict_types=1);

namespace Database\Factories\CRM\Customer;

use App\Models\CRM\Customer\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 *
 * @method Contact create(array $attributes = [])
 * @method Contact make(array $attributes = [])
 */
class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->email(),
            'phone1' => fake()->phoneNumber(),
            'phone2' => fake()->phoneNumber(),
        ];
    }
}
