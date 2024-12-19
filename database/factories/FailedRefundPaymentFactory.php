<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CRM\Customer\Account;
use App\Models\FailedRefundPayment;
use App\Models\Payment;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailedRefundPayment>
 *
 * @method static FailedRefundPayment create(array $attributes = [])
 * @method static FailedRefundPayment make(array $attributes = [])
 * @method FailedRefundPayment withoutRelationships()
 * @method FailedRefundPayment|Collection<int, FailedRefundPayment> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class FailedRefundPaymentFactory extends Factory
{
    use WithoutRelationships;
    use WithRelationships;

    protected $model = FailedRefundPayment::class;

    /**
     * @return array
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomNumber(),
            'failed_at' => $this->faker->dateTime(),
            'failure_reason' => $this->faker->word(),
            'report_sent_at' => $this->faker->dateTime(),
            'original_payment_id' => Payment::factory(),
            'account_id' => Account::factory(),
            'refund_payment_id' => Payment::factory(),
        ];
    }
}
