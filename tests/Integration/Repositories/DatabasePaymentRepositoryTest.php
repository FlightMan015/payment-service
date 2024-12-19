<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\DatabasePaymentRepository;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\RepositoryFilterMethodTestTrait;
use Tests\TestCase;

class DatabasePaymentRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    use RepositoryFilterMethodTestTrait;

    private DatabasePaymentRepository $repository;
    private Payment|null $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app()->make(DatabasePaymentRepository::class);

        $this->payment = $this->createPaymentInDatabase();
    }

    private function createPaymentInDatabase(): Payment
    {
        return Payment::factory()->create();
    }

    #[Test]
    public function find_throws_not_found_exception_for_not_existing_entity(): void
    {
        $paymentId = Str::uuid()->toString();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment.not_found', ['id' => $paymentId]));
        $this->repository->find(paymentId: $paymentId);
    }

    #[Test]
    public function find_return_correct_payment(): void
    {
        $columns = [
            'id',
            'payment_status_id',
            'amount',
            'created_at',
        ];
        $actual = $this->repository->find(paymentId: $this->payment->id, columns: $columns);

        $this->assertInstanceOf(Payment::class, $actual);
        $this->assertNotTrue(array_diff($columns, array_keys($actual->toArray())));
    }

    #[Test]
    public function find_with_ledger_types_returns_payment(): void
    {
        $ledgerOnlyPayment = Payment::factory()->create(['payment_type_id' => PaymentTypeEnum::CHECK]);

        $columns = [
            'id',
            'payment_status_id',
            'amount',
            'created_at',
        ];
        $actual = $this->repository->findWithLedgerTypes(paymentId: $ledgerOnlyPayment->id);

        $this->assertInstanceOf(Payment::class, $actual);
        $this->assertEmpty(array_diff($columns, array_keys($actual->toArray())));
    }

    #[Test]
    public function it_updates_corresponding_fields_if_correct_data_provided_for_check_payment(): void
    {
        $ledgerOnlyPayment = Payment::factory()->create(['payment_type_id' => PaymentTypeEnum::CHECK]);

        $testDate = Carbon::now()->toDateString();
        $testAmount = $this->payment->amount + 100;
        $data = [
            'amount' => $testAmount,
            'check_date' => $testDate,
        ];
        $actual = $this->repository->update(payment: $ledgerOnlyPayment, attributes: $data);

        $this->assertInstanceOf(Payment::class, $actual);
        $this->assertEquals($actual->amount, $testAmount);
        $this->assertEquals($actual->processed_at, $testDate);
    }

    #[Test]
    public function it_throws_exception_for_not_existing_result_for_ledger_types(): void
    {
        $nonExistingUUID = Str::uuid()->toString();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment.not_found', ['id' => $nonExistingUUID]));

        $this->repository->findWithLedgerTypes(paymentId: $nonExistingUUID);
    }

    #[Test]
    public function it_throws_unprocessable_exception_for_not_compatible_result_for_ledger_types(): void
    {
        $ccPayment = Payment::factory()->create(['payment_type_id' => PaymentTypeEnum::CC]);

        $this->expectException(UnprocessableContentException::class);
        $this->expectExceptionMessage(
            __(
                key: 'messages.payment.not_found_for_ledger_type',
                replace: ['type' => implode(', ', PaymentTypeEnum::ledgerOnlyNames())]
            )
        );

        $this->repository->findWithLedgerTypes(paymentId: $ccPayment->id);
    }

    #[Test]
    public function get_latest_successful_payment_for_invoices_returns_null_when_no_matching_payment_found(): void
    {
        $accountId = Str::uuid()->toString();
        $invoiceIds = [Str::uuid()->toString(), Str::uuid()->toString()];

        $result = $this->repository->getLatestSuccessfulPaymentForInvoices($accountId, $invoiceIds);

        $this->assertNull($result);
    }

    #[Test]
    public function get_latest_successful_payment_for_invoices_returns_null_when_matching_payment_has_different_status(): void
    {
        $account = Account::factory()->create();
        $invoiceIds = [Invoice::factory()->create()->id];

        $payment = Payment::factory()->for($account)->create(['payment_status_id' => PaymentStatusEnum::TERMINATED->value]);
        foreach($invoiceIds as $invoiceId) {
            $payment->invoices()->create([
                'invoice_id' => $invoiceId,
                'amount' => 100
            ]);
        }

        $result = $this->repository->getLatestSuccessfulPaymentForInvoices($account->id, $invoiceIds);

        $this->assertNull($result);
    }

    #[Test]
    public function get_latest_successful_payment_for_invoices_returns_payment_when_matching_payment_found(): void
    {
        $account = Account::factory()->create();
        $invoiceIds = [Invoice::factory()->create()->id];

        $payment = Payment::factory()->for($account)->create([
            'is_batch_payment' => true,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
        ]);
        foreach ($invoiceIds as $invoiceId) {
            $payment->invoices()->create([
                'invoice_id' => $invoiceId,
                'amount' => 100
            ]);
        }

        $result = $this->repository->getLatestSuccessfulPaymentForInvoices($account->id, $invoiceIds);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals($payment->id, $result->id);
    }

    #[Test]
    public function get_external_refund_without_transactions_for_area_returns_correct_payments(): void
    {
        $validPaymentsQuantity = 3;
        $alreadyProcessedPaymentsQuantity = 5;
        $paymentsWithTransactionsQuantity = 2;
        $paymentsWithOriginalPaymentsThatWereCreatedNotInPaymentServiceQuantity = 4;
        $paymentsThatWereNotCreatedInPestRoutesQuantity = 3;

        $area = Area::factory()->create();
        $account = Account::factory()->for($area)->create();
        $paymentMethod = PaymentMethod::factory()->for($account)->create();
        $originalPaymentFromPaymentService = Payment::factory()->for($account)->for($paymentMethod)->create([
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'pestroutes_created_by_crm' => true,
        ]);
        $originalPaymentNotFromPaymentService = Payment::factory()->for($account)->for($paymentMethod)->create([
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'pestroutes_created_by_crm' => false,
        ]);

        $validPayments = Payment::factory($validPaymentsQuantity)->for($account)->for($paymentMethod)->create([
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'original_payment_id' => $originalPaymentFromPaymentService->id,
            'pestroutes_created_by_crm' => false,
            'pestroutes_refund_processed_at' => null,
        ]);
        $alreadyProcessedPayments = Payment::factory($alreadyProcessedPaymentsQuantity)->for($account)->for($paymentMethod)->create([
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'original_payment_id' => $originalPaymentFromPaymentService->id,
            'pestroutes_created_by_crm' => false,
            'pestroutes_refund_processed_at' => now(),
        ]);
        $paymentsWithTransactions = Payment::factory($paymentsWithTransactionsQuantity)->for($account)->for($paymentMethod)->create([
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'original_payment_id' => $originalPaymentFromPaymentService->id,
            'pestroutes_created_by_crm' => false,
        ]);
        $paymentsWithTransactions->collect()->each(static function ($payment) {
            $payment->transactions()->create([
                'payment_id' => $payment->id,
                'transaction_type_id' => TransactionTypeEnum::CREDIT->value,
                'gateway_transaction_id' => random_int(1000000, 99999999),
                'gateway_response_code' => 0,
            ]);
        });
        $paymentsWithOriginalPaymentsThatWereCreatedNotInPaymentService = Payment::factory($paymentsWithOriginalPaymentsThatWereCreatedNotInPaymentServiceQuantity)
            ->for($account)
            ->for($paymentMethod)
            ->create([
                'payment_status_id' => PaymentStatusEnum::CREDITED->value,
                'payment_type_id' => PaymentTypeEnum::CC->value,
                'original_payment_id' => $originalPaymentNotFromPaymentService->id,
                'pestroutes_created_by_crm' => false,
            ]);
        $paymentsThatWereNotCreatedInPestRoutes = Payment::factory($paymentsThatWereNotCreatedInPestRoutesQuantity)
            ->for($account)
            ->for($paymentMethod)
            ->create([
                'payment_status_id' => PaymentStatusEnum::CREDITED->value,
                'payment_type_id' => PaymentTypeEnum::CC->value,
                'pestroutes_created_by_crm' => true,
            ]);

        $payments = $this->repository->getExternalRefundsWithoutTransactionsForArea(areaId: $area->id, page: 1, quantity: 100);

        $this->assertCount(expectedCount: $validPaymentsQuantity, haystack: $payments);
        $this->assertEquals($validPayments->collect()->value('id'), $payments->value('id'));
    }

    #[Test]
    public function it_returns_not_synchronized_payments_correctly(): void
    {
        $account = Account::factory()->create();
        Payment::factory()->for($account)->create([
            'external_ref_id' => null,
            'pestroutes_created_by_crm' => true,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
        ]);

        Payment::factory()->for($account)->create([
            'external_ref_id' => 1000,
            'pestroutes_created_by_crm' => true,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
        ]);

        Payment::factory()->for($account)->create([
            'external_ref_id' => null,
            'pestroutes_created_by_crm' => false,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
        ]);

        Payment::factory()->for($account)->create([
            'external_ref_id' => null,
            'pestroutes_created_by_crm' => false,
            'payment_status_id' => PaymentStatusEnum::SUSPENDED,
        ]);

        $this->assertCount(1, $this->repository->getNonSynchronisedPayments()->get());
    }

    public function get_latest_suspended_or_terminated_payment_for_original_payment_returns_null_when_no_matching_payment_found(): void
    {
        $accountId = Str::uuid()->toString();
        $originalPaymentId = Str::uuid()->toString();

        $result = $this->repository->getLatestSuspendedOrTerminatedPaymentForOriginalPayment($accountId, $originalPaymentId);

        $this->assertNull($result);
    }

    #[Test]
    public function get_latest_suspended_or_terminated_payment_for_original_payment_returns_null_when_matching_payment_has_different_status(): void
    {
        $account = Account::factory()->create();

        $originalPayment = Payment::factory()->for($account)->create(['payment_status_id' => PaymentStatusEnum::CAPTURED->value]);
        Payment::factory()->for($account)->create([
            'is_batch_payment' => true,
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'original_payment_id' => $originalPayment->id,
        ]);

        $result = $this->repository->getLatestSuspendedOrTerminatedPaymentForOriginalPayment($account->id, $originalPayment->id);

        $this->assertNull($result);
    }

    #[Test]
    public function get_latest_suspended_or_terminated_payment_for_original_payment_returns_payment_when_matching_payment_found(): void
    {
        $account = Account::factory()->create();

        $originalPayment = Payment::factory()->for($account)->create(['payment_status_id' => PaymentStatusEnum::CAPTURED->value]);
        $payment = Payment::factory()->for($account)->create([
            'is_batch_payment' => true,
            'payment_status_id' => PaymentStatusEnum::TERMINATED,
            'original_payment_id' => $originalPayment->id,
        ]);

        $result = $this->repository->getLatestSuspendedOrTerminatedPaymentForOriginalPayment($account->id, $originalPayment->id);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals($payment->id, $result->id);
    }

    #[Test]
    public function get_not_fully_settled_ach_payments_returns_correct_payments(): void
    {
        $account = Account::factory()->create([
            'area_id' => Area::factory()->create()->id,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'account_id' => $account->id,
            'payment_type_id' => PaymentTypeEnum::ACH->value,
        ]);
        $payment = Payment::factory()->count(3)->create([
            'account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'processed_at' => Carbon::now(),
        ]);
        // Payments without CAPTURED status. Should not be returned
        Payment::factory()->count(2)->create([
            'account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_status_id' => PaymentStatusEnum::AUTH_CAPTURING,
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'processed_at' => Carbon::now(),
        ]);
        // Tokenex payments. Should not be returned
        Payment::factory()->count(2)->create([
            'account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT->value,
            'processed_at' => Carbon::now(),
        ]);
        // CC payments. Should not be returned
        Payment::factory()->count(2)->create([
            'account_id' => $account->id,
            'payment_method_id' => PaymentMethod::factory()->cc()->create([
                'account_id' => $account->id,
            ]),
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT->value,
            'processed_at' => Carbon::now(),
        ]);
        // Payment has a returned payment. Should not be returned
        $tobeReturnedPayment = Payment::factory()->create([
            'account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'processed_at' => Carbon::now(),
        ]);
        Payment::factory()->create([
            'account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_status_id' => PaymentStatusEnum::RETURNED,
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'processed_at' => Carbon::now(),
            'original_payment_id' => $tobeReturnedPayment->id,
        ]);

        $result = $this->repository->getNotFullySettledAchPayments(
            processedAtFrom: Carbon::now()->subDays(1),
            processedAtTo: Carbon::now(),
            page: 1,
            quantity: 100,
            areaId: $account->area_id,
        );

        $this->assertCount(3, $result);
        $this->assertEquals($payment->first()->id, $result->first()->id);
    }

    protected function getEntity(): Model
    {
        return $this->payment;
    }

    protected function getRepository(): mixed
    {
        return $this->repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->repository, $this->payment);
    }
}
