<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\AccountUpdaterAttempt;
use App\Models\CRM\Customer\Account;
use App\Models\Gateway;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractModelTest;

class PaymentMethodTest extends AbstractModelTest
{
    protected function getTableName(): string
    {
        return 'billing.payment_methods';
    }

    protected function getColumnList(): array
    {
        return [
            'id',
            'external_ref_id',
            'account_id',
            'payment_gateway_id',
            'payment_type_id',
            'ach_account_number_encrypted',
            'ach_routing_number',
            'ach_account_type',
            'ach_token',
            'cc_token',
            'cc_type',
            'cc_expiration_month',
            'cc_expiration_year',
            'name_on_account',
            'address_line1',
            'address_line2',
            'email',
            'city',
            'province',
            'postal_code',
            'country_code',
            'is_primary',
            'last_four',
            'payment_hold_date',
            'pestroutes_customer_id',
            'pestroutes_created_by',
            'pestroutes_payment_method_id',
            'pestroutes_status_id',
            'pestroutes_ach_account_type_id',
            'pestroutes_ach_check_type_id',
            'pestroutes_payment_hold_date',
            'pestroutes_created_at',
            'pestroutes_updated_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
            'ach_bank_name',
        ];
    }

    #[Test]
    public function payment_method_belongs_to_gateway(): void
    {
        $gateway = Gateway::inRandomOrder()->first();
        $paymentMethod = PaymentMethod::factory()->create(['payment_gateway_id' => $gateway->id]);

        $this->assertInstanceOf(expected: Gateway::class, actual: $paymentMethod->gateway);
        $this->assertSame($gateway->id, $paymentMethod->payment_gateway_id);
    }

    #[Test]
    public function payment_method_belongs_to_payment_type(): void
    {
        $paymentType = PaymentType::inRandomOrder()->first();
        $paymentMethod = PaymentMethod::factory()->create(['payment_type_id' => $paymentType->id]);

        $this->assertInstanceOf(PaymentType::class, $paymentMethod->type);
        $this->assertSame($paymentType->id, $paymentMethod->payment_type_id);
    }

    #[Test]
    public function payment_method_belongs_to_account_updater_attempts(): void
    {
        $accountUpdaterAttempt = AccountUpdaterAttempt::factory()->create();
        $paymentMethod = PaymentMethod::factory()->cc()->create(attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]);
        $accountUpdaterAttempt->methods()->attach(id: $paymentMethod->id, attributes: [
            'original_token' => $paymentMethod->cc_token,
            'original_expiration_year' => $paymentMethod->cc_expiration_year,
            'original_expiration_month' => $paymentMethod->cc_expiration_month,
            'sequence_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertContainsOnlyInstancesOf(AccountUpdaterAttempt::class, $paymentMethod->accountUpdaterAttempts);
        $this->assertSame($accountUpdaterAttempt->id, $paymentMethod->accountUpdaterAttempts->first()->id);
    }

    #[Test]
    public function it_returns_correct_primary_payment_method_for_account(): void
    {
        $account = Account::factory()->create();

        PaymentMethod::factory()->for($account)->count(count: 2)->create(['is_primary' => false]); // not primary
        $primaryPaymentMethod = PaymentMethod::factory()->for($account)->create(['is_primary' => true]);
        PaymentMethod::factory()->count(count: 3)->for($account)->create(['is_primary' => false]); // also not primary

        $this->assertTrue($primaryPaymentMethod->is(PaymentMethod::primaryForAccount(accountId: $account->id)->first()));
        $this->assertTrue($primaryPaymentMethod->is_primary);
    }

    #[Test]
    public function it_returns_correct_autopay_payment_method_for_account(): void
    {
        $account = Account::factory()->create();

        $autopayPaymentMethod = PaymentMethod::factory()->for($account)->create(['is_primary' => false]); // autopay
        PaymentMethod::factory()->for($account)->create(['is_primary' => true]); // primary
        PaymentMethod::factory()->for($account)->count(count: 3)->create(['is_primary' => false]); // not primary

        $account->setAutoPayPaymentMethod(autopayPaymentMethod: $autopayPaymentMethod);

        $this->assertTrue($autopayPaymentMethod->is(PaymentMethod::autopayForAccount(accountId: $account->id)->first()));
        $this->assertTrue($autopayPaymentMethod->is_autopay);
    }
}
