<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Helpers\SodiumEncryptHelper;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\CreditCardTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Faker\Provider\en_US\Address;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 *
 * @method static PaymentMethod create(array $attributes = [])
 * @method static PaymentMethod make(array $attributes = [])
 * @method PaymentMethod withoutRelationships()
 * @method PaymentMethod|Collection<int, PaymentMethod> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class PaymentMethodFactory extends Factory
{
    use WithoutRelationships;
    use WithRelationships;

    /**
     * Define the model's default state.
     *
     * @throws \SodiumException
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentType = $this->faker->randomElement(PaymentTypeEnum::cases());

        return [
            'id' => Str::uuid()->toString(),
            'payment_type_id' => $paymentType->value,
            'payment_gateway_id' => $this->faker->randomElement(PaymentGatewayEnum::realGateways())->value,
            'account_id' => Account::factory(),
            'name_on_account' => $this->faker->name(),
            'address_line1' => Str::of($this->faker->address())->limit(25),
            'address_line2' => Str::of($this->faker->address())->limit(25),
            'email' => $this->faker->email(),
            'city' => $this->faker->city(),
            'province' => Address::stateAbbr(),
            'postal_code' => $this->faker->postcode(),
            'country_code' => $this->faker->countryCode(),
            'external_ref_id' => $this->faker->randomNumber(nbDigits: 9),
            'ach_token' => $paymentType === PaymentTypeEnum::ACH ? $this->faker->bothify(string: '??#??#??-?##?-#??#-?##?-?##???#??##?') : null,
            'ach_account_type' => $paymentType === PaymentTypeEnum::ACH ? $this->faker->randomElement(AchAccountTypeEnum::cases())->value : null,
            'ach_account_number_encrypted' => $paymentType === PaymentTypeEnum::ACH ? SodiumEncryptHelper::encrypt((string)$this->faker->randomNumber(nbDigits: 9)) : null,
            'ach_routing_number' => $paymentType === PaymentTypeEnum::ACH ? $this->faker->randomNumber(nbDigits: 9) : null,
            'ach_bank_name' => $paymentType === PaymentTypeEnum::ACH ? $this->faker->name() : null,
            'cc_token' => $paymentType === PaymentTypeEnum::CC ? strtoupper($this->faker->bothify(string: '??#??#??-?##?-#??#-?##?-?##???#??##?')) : null,
            'cc_type' => $paymentType === PaymentTypeEnum::CC ? $this->faker->randomElement(CreditCardTypeEnum::cases())->value : null,
            'cc_expiration_year' => $paymentType === PaymentTypeEnum::CC ? $this->faker->year() : null,
            'cc_expiration_month' => $paymentType === PaymentTypeEnum::CC ? $this->faker->month(max: 12) : null,

            'last_four' => $this->faker->randomNumber(nbDigits: 4, strict: true),
            'is_primary' => $this->faker->boolean(),
        ];
    }

    /**
     * @return self
     */
    public function ach(): self
    {
        return $this->state(state: fn () => [
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'ach_account_type' => $this->faker->randomElement(AchAccountTypeEnum::cases())->value,
            'ach_account_number_encrypted' => SodiumEncryptHelper::encrypt((string)$this->faker->randomNumber(nbDigits: 9)),
            'ach_routing_number' => $this->faker->randomNumber(nbDigits: 9),
            'ach_bank_name' => $this->faker->words(nb: 2, asText: true),
            'cc_token' => null,
            'cc_expiration_year' => null,
            'cc_expiration_month' => null,
        ]);
    }

    /**
     * @return self
     */
    public function cc(): self
    {
        return $this->state(state: fn () => [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'ach_token' => null,
            'ach_account_type' => null,
            'ach_account_number_encrypted' => null,
            'ach_routing_number' => null,
            'ach_bank_name' => null,
            'cc_token' => strtoupper(string: $this->faker->bothify(string: '??#??#??-?##?-#??#-?##?-?##???#??##?')),
            'cc_type' => $this->faker->randomElement(CreditCardTypeEnum::cases())->value,
            'cc_expiration_year' => (int) date('Y', strtotime('+' . (1 + $this->faker->randomDigit()) . 'year')),
            'cc_expiration_month' => $this->faker->month(max: 12),
        ]);
    }
}
