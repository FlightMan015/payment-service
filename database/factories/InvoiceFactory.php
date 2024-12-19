<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Subscription;
use App\Models\Invoice;
use App\PaymentProcessor\Enums\CurrencyCodeEnum;
use Carbon\Carbon;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 *
 * @method Invoice create(array $attributes = [])
 * @method Invoice make(array $attributes = [])
 * @method Invoice withoutRelationships()
 * @method Invoice|Collection<int, Invoice> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class InvoiceFactory extends Factory
{
    use WithoutRelationships;
    use WithRelationships;

    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'account_id' => Account::factory(),
            'subscription_id' => Subscription::factory(),
            'is_active' => $this->faker->boolean(),
            'subtotal' => $this->faker->randomNumber(2),
            'tax_rate' => $this->faker->randomFloat(2),
            'total' => $this->faker->randomNumber(2),
            'balance' => $this->faker->randomNumber(2),
            'currency_code' => $this->faker->randomElement(array_column(CurrencyCodeEnum::cases(), 'value')),
            'service_charge' => $this->faker->randomNumber(),
            'invoiced_at' => Carbon::now()->toISOString(),
            'created_at' => Carbon::now()->subMinute(),
            'updated_at' => Carbon::now(),
        ];
    }
}
