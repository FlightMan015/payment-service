<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Gateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentInvoice;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractModelTest;

class PaymentTest extends AbstractModelTest
{
    protected function getTableName(): string
    {
        return 'billing.payments';
    }

    protected function getColumnList(): array
    {
        return [
            'id',
            'external_ref_id',
            'account_id',
            'payment_type_id',
            'payment_status_id',
            'payment_method_id',
            'payment_gateway_id',
            'currency_code',
            'amount',
            'applied_amount',
            'notes',
            'processed_at',
            'notification_id',
            'notification_sent_at',
            'is_office_payment',
            'is_collection_payment',
            'is_write_off',
            'pestroutes_customer_id',
            'pestroutes_created_by',
            'pestroutes_created_at',
            'pestroutes_updated_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
            'pestroutes_json',
        ];
    }

    #[Test]
    public function payment_belongs_to_gateway(): void
    {
        $gateway = Gateway::inRandomOrder()->first();
        $payment = Payment::factory()->create(attributes: ['payment_gateway_id' => $gateway->id]);

        $this->assertInstanceOf(expected: Gateway::class, actual: $payment->gateway);
        $this->assertSame($gateway->id, $payment->payment_gateway_id);
    }

    #[Test]
    public function payment_belongs_to_payment_method(): void
    {
        $paymentMethod = PaymentMethod::factory()->create();
        $payment = Payment::factory()->for($paymentMethod)->create();

        $this->assertInstanceOf(expected: PaymentMethod::class, actual: $payment->paymentMethod);
        $this->assertSame($paymentMethod->id, $payment->payment_method_id);
    }

    #[Test]
    public function payment_belongs_to_payment_type(): void
    {
        $paymentType = PaymentType::inRandomOrder()->first();
        $payment = Payment::factory()->create(['payment_type_id' => $paymentType->id]);

        $this->assertInstanceOf(expected: PaymentType::class, actual: $payment->type);
        $this->assertSame($paymentType->id, $payment->payment_type_id);
    }

    #[Test]
    #[DataProvider('amountInCentsProvider')]
    public function it_converts_amount_in_cents_to_decimal(int $cents, float $expected): void
    {
        $payment = Payment::factory()->make();

        $payment->amount = $cents;

        $decimalAmount = $payment->getDecimalAmount();

        $this->assertEquals($expected, $decimalAmount);
        // 'double' is used for float on gettype() method: https://www.php.net/manual/en/function.gettype.php#refsect1-function.gettype-returnvalues
        $this->assertEquals('double', gettype($decimalAmount));
    }

    #[Test]
    public function it_filters_payment_by_account_id_correctly(): void
    {
        $account = Account::factory()->create();
        $searchablePayments = Payment::factory()->for($account)->count(6)->create();
        $notReturnedPayments = Payment::factory()->count(2)->create();

        $filteredData = Payment::filtered(['account_id' => $account->id])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
        $this->assertSame($filteredData->first()->account_id, $account->id);
    }

    #[Test]
    public function it_filters_payment_by_payment_method_id_correctly(): void
    {
        $account = Account::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($account)->create();
        $searchablePayments = Payment::factory()->for($paymentMethod)->for($account)->count(6)->create();
        $notReturnedPayments = Payment::factory()->for($account)->count(2)->create();

        $filteredData = Payment::filtered([
            'payment_method_id' => $paymentMethod->id,
            'account_id' => $account->id,
        ])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
        $this->assertSame($filteredData->first()->payment_method_id, $paymentMethod->id);
    }

    #[Test]
    public function it_filters_payment_by_payment_status_id_correctly(): void
    {
        $searchablePayments = Payment::factory()->count(6)->create([
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
        ]);
        $notReturnedPayments = Payment::factory()->count(2)->create([
            'payment_status_id' => PaymentStatusEnum::CANCELLED->value,
        ]);

        $filteredData = Payment::filtered(['payment_status' => PaymentStatusEnum::CAPTURED->name])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
        $this->assertSame($filteredData->first()->payment_status_id, $searchablePayments->first()->payment_status_id);
    }

    #[Test]
    public function it_filters_payment_by_area_id_correctly(): void
    {
        $area = Area::factory()->create();
        $account = Account::factory()->for($area)->create();
        $searchablePayments = Payment::factory()->for($account)->count(6)->create();
        $notReturnedPayments = Payment::factory()->count(2)->create();

        $filteredData = Payment::with(relations: 'account')->filtered(['area_id' => $area->id])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
        $this->assertSame($filteredData->first()->account->area_id, $area->id);
    }

    #[Test]
    public function it_filters_payment_by_invoice_id_correctly(): void
    {
        $invoice = Invoice::factory()->create();
        $payments = Payment::factory()->count(6)->create();
        PaymentInvoice::factory()->for($invoice)->for($payments->first())->create(['amount' => $invoice->balance]);

        $filteredData = Payment::filtered(['invoice_id' => $invoice->id])->get();

        $this->assertCount(1, $filteredData);
        $this->assertSame($filteredData->first()->id, $payments->first()->id);
    }

    #[Test]
    public function it_filters_payment_by_amount_range_correctly(): void
    {
        $searchablePayments = Payment::factory()->count(6)->create(['amount' => rand(1000, 5742)]);
        $notReturnedPayments = Payment::factory()->count(2)->create(['amount' => 998]);

        $filteredData = Payment::filtered(['amount_from' => 999, 'amount_to' => 100000])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
    }

    #[Test]
    public function it_filters_payment_by_processed_at_range_correctly(): void
    {
        $searchablePayments = Payment::factory()->count(6)->create([
            'processed_at' => Carbon::now()->addDays(rand(1, 10)),
        ]);
        $notReturnedPayments = Payment::factory()->count(2)->create([
            'processed_at' => Carbon::now()->subDays(rand(1, 10)),
        ]);

        $filteredData = Payment::filtered([
            'date_from' => Carbon::now(),
            'date_to' => Carbon::now()->addDays(11),
        ])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
    }

    #[Test]
    public function it_filters_payment_by_account_first_name_correctly(): void
    {
        $account = Account::factory()->create();
        $searchablePayments = Payment::factory()->for($account)->count(6)->create([
            'processed_at' => Carbon::now()->addDays(rand(1, 10)),
        ]);
        $notReturnedPayments = Payment::factory()->count(2)->create([
            'processed_at' => Carbon::now()->subDays(rand(1, 10)),
        ]);

        $filteredData = Payment::filtered([
            'first_name' => $account->billingContact->first_name,
        ])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
    }

    #[Test]
    public function it_filters_payment_by_account_last_name_correctly(): void
    {
        $account = Account::factory()->create();
        $searchablePayments = Payment::factory()->for($account)->count(6)->create([
            'processed_at' => Carbon::now()->addDays(rand(1, 10)),
        ]);
        $notReturnedPayments = Payment::factory()->count(2)->create([
            'processed_at' => Carbon::now()->subDays(rand(1, 10)),
        ]);

        $filteredData = Payment::filtered([
            'last_name' => $account->billingContact->last_name,
        ])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
    }

    #[Test]
    public function it_gets_the_latest_check_status_transaction_by_transaction_type(): void
    {
        $account = Account::factory()->create();
        $payment = Payment::factory()->create([
            'account_id' => $account->id,
        ]);
        $returnTransaction = $payment->transactions()->create([
            'transaction_type_id' => TransactionTypeEnum::CHECK_STATUS->value,
            'gateway_transaction_id' => '123456',
            'gateway_response_code' => WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_RETURNED->value,
        ]);

        $latestReturnTransaction = $payment->transactionByTransactionType(TransactionTypeEnum::CHECK_STATUS);

        $this->assertSame($returnTransaction->id, $latestReturnTransaction->id);
    }

    /**
     * We expect rounding to two decimal places
     */
    public static function amountInCentsProvider(): array
    {
        return [
            '100_cents' => [
                'cents' => 100,
                'expected' => 1.00
            ],
            '1000_cents' => [
                'cents' => 1000,
                'expected' => 10.00
            ],
            '500_cents' => [
                'cents' => 500,
                'expected' => 5.00
            ],
            '1234_cents' => [
                'cents' => 1234,
                'expected' => 12.34
            ],
            '1334455_cents' => [
                'cents' => 1334455,
                'expected' => 13344.55 // Expect rounding to two decimal places
            ],
        ];
    }
}
