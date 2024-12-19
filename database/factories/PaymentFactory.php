<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 *
 * @method static Payment create(array $attributes = [])
 * @method static Payment make(array $attributes = [])
 * @method Payment withoutRelationships()
 * @method Payment|Collection<int, Payment> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class PaymentFactory extends Factory
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
            'account_id' => Account::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'external_ref_id' => $this->faker->unique()->randomNumber(4),
            'payment_type_id' => $this->faker->randomElement(PaymentTypeEnum::cases())->value,
            'payment_status_id' => $this->faker->randomElement(PaymentStatusEnum::cases())->value,
            'payment_gateway_id' => $this->faker->randomElement(PaymentGatewayEnum::cases())->value,
            'amount' => $this->faker->randomNumber(nbDigits: 5),
            'applied_amount' => 0,
            'currency_code' => 'USD',
            'processed_at' => $this->faker->dateTime(),
        ];
    }

    /**
     * @return self
     */
    public function cc(): self
    {
        return $this->state(state: fn () => [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_month' => $this->faker->month(max: 12), // See Note on static_lambda rule section in Readme
        ]);
    }
}
