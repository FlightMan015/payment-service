<?php

declare(strict_types=1);

namespace Database\Factories\CRM\Customer;

use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Address;
use App\Models\CRM\Customer\Contact;
use App\Models\CRM\FieldOperations\Area;
use App\Models\CRM\Organization\Dealer;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 *
 * @method Account create(array $attributes = [])
 * @method Account make(array $attributes = [])
 * @method Account withoutRelationships()
 * @method Account|Collection<int, Account> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class AccountFactory extends Factory
{
    use WithoutRelationships;
    use WithRelationships;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'is_active' => true,
            'contact_id' => Contact::factory(),
            'external_ref_id' => fake()->randomNumber(nbDigits: 9),
            'billing_contact_id' => Contact::factory(),
            'billing_address_id' => Address::factory(),
            'pestroutes_created_by' => null,
            'service_address_id' => Address::factory(),
            'area_id' => Area::factory(),
            'dealer_id' => Dealer::factory(),
        ];
    }
}
