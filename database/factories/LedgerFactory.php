<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CRM\Customer\Account;
use App\Models\Ledger;
use App\Models\PaymentMethod;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ledger>
 *
 * @method Ledger create(array $attributes = [])
 * @method Ledger make(array $attributes = [])
 * @method Ledger withoutRelationships()
 * @method Ledger|Collection<int, Ledger> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class LedgerFactory extends Factory
{
    use WithoutRelationships;
    use WithRelationships;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'balance' => $this->faker->randomNumber(nbDigits: 5),
            'autopay_payment_method_id' => PaymentMethod::factory(),
        ];
    }
}
