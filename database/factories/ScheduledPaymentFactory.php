<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledPayment>
 *
 * @method static ScheduledPayment create(array $attributes = [])
 * @method static ScheduledPayment make(array $attributes = [])
 * @method ScheduledPayment withoutRelationships()
 * @method ScheduledPayment|Collection<int, ScheduledPayment> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class ScheduledPaymentFactory extends Factory
{
    use WithRelationships;
    use WithoutRelationships;

    protected $model = ScheduledPayment::class;

    /**
     * @return array
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'status_id' => $this->faker->randomElement(ScheduledPaymentStatusEnum::cases())->value,
            // 'trigger_id' => $this->faker->randomElement(ScheduledPaymentTriggerEnum::cases())->value, // TODO: uncomment after implementing more than 1 trigger
            'trigger_id' => $this->faker->randomElement([ScheduledPaymentTriggerEnum::InitialServiceCompleted])->value,
            'metadata' => ['subscription_id' => $this->faker->uuid()],
            'amount' => $this->faker->randomNumber(),
            'payment_id' => Payment::factory(),
        ];
    }
}
