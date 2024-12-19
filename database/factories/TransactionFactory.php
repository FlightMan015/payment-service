<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 *
 * @method static Transaction create(array $attributes = [])
 * @method static Transaction make(array $attributes = [])
 * @method static Transaction withoutRelationships()
 * @method static Transaction|Collection<int, Transaction> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class TransactionFactory extends Factory
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
            'id' => Str::uuid()->toString(),
            'payment_id' => Payment::factory(),
            'transaction_type_id' => $this->faker->randomElement(TransactionTypeEnum::cases()),
            'gateway_transaction_id' => $this->faker->regexify('[A-Z-]{20}'),
            'gateway_response_code' => $this->faker->word,
        ];
    }
}
