<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Helpers\MoneyHelper;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;
use App\Services\Payment\Refund\RefundElectronicPaymentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Helper\TableSeparator;
use Tests\Unit\UnitTestCase;

class RefundPaymentsCommandTest extends UnitTestCase
{
    #[Test]
    public function it_refunds_single_payment_successfully(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type' => PaymentTypeEnum::CC,
            ],
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::CC->value,
                    'name' => PaymentTypeEnum::CC->name,
                ]),
            ]
        );
        $transaction = Transaction::factory()->makeWithRelationships(
            attributes: ['transaction_type_id' => TransactionTypeEnum::CREDIT->value],
            relationships: ['payment' => $payment],
        );
        /** @var Payment|MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')
            ->with([OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE])
            ->andReturn($transaction);
        $paymentId = $payment->id;

        $this->mockPaymentRepository(payment: $payment);
        $this->mockTransactionRepository(transaction: $transaction);
        $this->mockRefundService(paymentId: $paymentId, isSuccess: true, transactionId: $transaction->id);

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$paymentId]])
            ->expectsOutput(output: "Refunded Payment ID: $paymentId successfully")
            ->expectsTable(
                headers: ['Payment Type', 'Successfully #', 'Successfully $', 'Failed #', 'Failed $'],
                rows: [
                    [
                        'type' => 'Credit Card',
                        'successful_count' => 1,
                        'successful_amount' => MoneyHelper::convertToDecimal($payment->amount),
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                    [
                        'type' => 'ACH',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                    new TableSeparator(),
                    [
                        'type' => 'Total',
                        'successful_count' => 1,
                        'successful_amount' => MoneyHelper::convertToDecimal($payment->amount),
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                ]
            )
            ->assertSuccessful();
    }

    #[Test]
    public function it_refunds_multiple_payments_successfully(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $ccPayment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type' => PaymentTypeEnum::CC,
            ],
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::CC->value,
                    'name' => PaymentTypeEnum::CC->name,
                ]),
            ]
        );
        $ccTransaction = Transaction::factory()->makeWithRelationships(
            attributes: ['transaction_type_Id' => TransactionTypeEnum::CREDIT->value],
            relationships: ['payment' => $ccPayment],
        );
        /** @var Payment|MockInterface $ccPayment */
        $ccPayment = Mockery::mock($ccPayment)->makePartial();
        $ccPayment->shouldReceive('transactionForOperation')
            ->with([OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE])
            ->andReturn($ccTransaction);

        $achPayment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type' => PaymentTypeEnum::ACH,
            ],
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value,
                    'name' => PaymentTypeEnum::ACH->name,
                ]),
            ]
        );
        $achTransaction = Transaction::factory()->makeWithRelationships(
            attributes: ['transaction_type_Id' => TransactionTypeEnum::CREDIT->value],
            relationships: ['payment' => $achPayment],
        );
        /** @var Payment|MockInterface $achPayment */
        $achPayment = Mockery::mock($achPayment)->makePartial();
        $achPayment->shouldReceive('transactionForOperation')
            ->with([OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE])
            ->andReturn($achTransaction);

        /** @var Collection<int, Payment> $payments */
        $payments = collect([$ccPayment, $achPayment]);
        $paymentIds = array_map(callback: static fn (Payment $payment) => $payment->id, array: $payments->all());
        /** @var Collection<int, Transaction> $transactions */
        $transactions = collect([$ccTransaction, $achTransaction]);

        $this->mockPaymentRepositoryWithMultiplePayments($payments);
        $this->mockTransactionRepositoryWithMultipleTransactions($transactions);
        $this->mockRefundServiceWithMultiplePaymentsAndTransactions($payments, $transactions);

        $command = $this->artisan(command: 'refund:full', parameters: ['ids' => $paymentIds]);
        foreach ($paymentIds as $paymentId) {
            $command->expectsOutput(output: "Refunded Payment ID: $paymentId successfully");
        }
        $command->expectsTable(
            headers: ['Payment Type', 'Successfully #', 'Successfully $', 'Failed #', 'Failed $'],
            rows: [
                [
                    'type' => 'Credit Card',
                    'successful_count' => 1,
                    'successful_amount' => MoneyHelper::convertToDecimal($ccPayment->amount),
                    'failed_count' => 0,
                    'failed_amount' => 0,
                ],
                [
                    'type' => 'ACH',
                    'successful_count' => 1,
                    'successful_amount' => MoneyHelper::convertToDecimal($achPayment->amount),
                    'failed_count' => 0,
                    'failed_amount' => 0,
                ],
                new TableSeparator(),
                [
                    'type' => 'Total',
                    'successful_count' => 2,
                    'successful_amount' => MoneyHelper::convertToDecimal($ccPayment->amount + $achPayment->amount),
                    'failed_count' => 0,
                    'failed_amount' => 0,
                ],
            ]
        );
        $command->assertSuccessful();
    }

    #[Test]
    public function it_logs_error_if_payment_was_not_refunded_successfully(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type' => PaymentTypeEnum::ACH,
            ],
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value,
                    'name' => PaymentTypeEnum::ACH->name,
                ]),
            ]
        );
        /** @var Payment|MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')
            ->with([OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE])
            ->andReturnNull();
        $paymentId = $payment->id;

        $this->mockPaymentRepository(payment: $payment);
        $this->mockRefundService(paymentId: $paymentId, isSuccess: false, errorMessage: 'something wrong');

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$paymentId]])
            ->expectsOutput(output: sprintf('Error refunding Payment ID: %s, error: something wrong', $paymentId))
            ->expectsTable(
                headers: ['Payment Type', 'Successfully #', 'Successfully $', 'Failed #', 'Failed $'],
                rows: [
                    [
                        'type' => 'Credit Card',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                    [
                        'type' => 'ACH',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 1,
                        'failed_amount' => MoneyHelper::convertToDecimal($payment->amount),
                    ],
                    new TableSeparator(),
                    [
                        'type' => 'Total',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 1,
                        'failed_amount' => MoneyHelper::convertToDecimal($payment->amount),
                    ],
                ]
            )
            ->assertSuccessful();
    }

    #[Test]
    public function it_logs_error_if_payment_is_not_found(): void
    {
        $nonExistingPaymentId = Str::uuid()->toString();

        $this->mockPaymentRepository(payment: null);

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$nonExistingPaymentId]])
            ->expectsOutput(output: sprintf('Error refunding Payment ID: %s, reason: Payment not found', $nonExistingPaymentId))
            ->expectsTable(
                headers: ['Payment Type', 'Successfully #', 'Successfully $', 'Failed #', 'Failed $'],
                rows: [
                    [
                        'type' => 'Credit Card',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                    [
                        'type' => 'ACH',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                    new TableSeparator(),
                    [
                        'type' => 'Total',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                ]
            )
            ->assertSuccessful();
    }

    #[Test]
    public function it_logs_error_if_refund_payment_handler_throws_exception(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type' => PaymentTypeEnum::ACH,
            ],
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value,
                    'name' => PaymentTypeEnum::ACH->name,
                ]),
            ]
        );
        /** @var Payment|MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')
            ->with([OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE])
            ->andReturnNull();
        $paymentId = $payment->id;

        $this->mockPaymentRepository(payment: $payment);
        $this->mockRefundService(paymentId: $paymentId, isSuccess: false, exception: new \Exception('Test Exception'));

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$paymentId]])
            ->expectsOutput(output: sprintf('Error refunding Payment ID: %s, reason: Test Exception', $paymentId))
            ->expectsTable(
                headers: ['Payment Type', 'Successfully #', 'Successfully $', 'Failed #', 'Failed $'],
                rows: [
                    [
                        'type' => 'Credit Card',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 0,
                        'failed_amount' => 0,
                    ],
                    [
                        'type' => 'ACH',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 1,
                        'failed_amount' => MoneyHelper::convertToDecimal($payment->amount),
                    ],
                    new TableSeparator(),
                    [
                        'type' => 'Total',
                        'successful_count' => 0,
                        'successful_amount' => 0,
                        'failed_count' => 1,
                        'failed_amount' => MoneyHelper::convertToDecimal($payment->amount),
                    ],
                ]
            )
            ->assertSuccessful();
    }

    #[Test]
    public function it_logs_error_if_attempting_to_refund_not_electronic_payment(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type' => PaymentTypeEnum::CHECK,
            ],
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::CHECK->value,
                    'name' => PaymentTypeEnum::CHECK->name,
                ]),
            ]
        );
        /** @var Payment|MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')
            ->with([OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE])
            ->andReturnNull();
        $paymentId = $payment->id;

        $this->mockPaymentRepository(payment: $payment);

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$paymentId]])
            ->expectsOutput(output: sprintf('Error refunding Payment ID: %s, reason: Only electronic payments could be refunded', $paymentId))
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_if_days_allowed_argument_is_more_than_technical_limit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->artisan(
            command: 'refund:full',
            parameters: [
                'ids' => [Str::uuid()->toString()],
                '--days-allowed' => RefundElectronicPaymentService::ELECTRONIC_REFUND_DAYS_TECHNICAL_LIMIT + 1
            ]
        )
            ->assertFailed();
    }

    private function mockRefundService(
        string $paymentId,
        bool $isSuccess,
        \Throwable|null $exception = null,
        string|null $errorMessage = null,
        string|null $transactionId = null
    ): void {
        $service = Mockery::mock(RefundElectronicPaymentService::class);

        if (!is_null($exception)) {
            $service->allows('refund')->andThrow($exception);
        } else {
            $service->allows('refund')->andReturnUsing(function ($dto) use ($paymentId, $isSuccess, $errorMessage, $transactionId) {
                $this->assertInstanceOf(MakePaymentRefundDto::class, $dto);
                $this->assertEquals($paymentId, $dto->originalPayment->id);

                return new RefundPaymentResultDto(
                    isSuccess: $isSuccess,
                    status: $isSuccess ? PaymentStatusEnum::CREDITED : PaymentStatusEnum::DECLINED,
                    refundPaymentId: Str::uuid()->toString(),
                    transactionId: $transactionId,
                    errorMessage: $errorMessage
                );
            });
        }

        $this->app->instance(RefundElectronicPaymentService::class, $service);
    }

    private function mockPaymentRepository(Payment|null $payment): void
    {
        $paymentRepository = Mockery::mock(PaymentRepository::class);

        if (is_null($payment)) {
            $paymentRepository->allows('find')->andThrow(new ResourceNotFoundException('Payment not found'));
        } else {
            $paymentRepository->allows('find')->with($payment->id)->andReturns($payment);
        }

        $this->app->instance(PaymentRepository::class, $paymentRepository);
    }

    private function mockTransactionRepository(Transaction|null $transaction): void
    {
        $transactionRepository = Mockery::mock(PaymentTransactionRepository::class);

        if (is_null($transaction)) {
            $transactionRepository->allows('findById')->andThrow(new ResourceNotFoundException('Transaction not found'));
        } else {
            $transactionRepository->allows('findById')->with($transaction->id)->andReturns($transaction);
        }

        $this->app->instance(PaymentTransactionRepository::class, $transactionRepository);
    }

    /**
     * @param Collection<int, Payment> $payments
     */
    private function mockPaymentRepositoryWithMultiplePayments(Collection $payments): void
    {
        $paymentRepository = Mockery::mock(PaymentRepository::class);

        foreach ($payments as $payment) {
            $paymentRepository->allows('find')->with($payment->id)->andReturns($payment);
        }

        $this->app->instance(PaymentRepository::class, $paymentRepository);
    }

    /**
     * @param Collection<int, Transaction> $transactions
     */
    private function mockTransactionRepositoryWithMultipleTransactions(Collection $transactions): void
    {
        $transactionRepository = Mockery::mock(PaymentTransactionRepository::class);

        foreach ($transactions as $transaction) {
            $transactionRepository->allows('findById')->with($transaction->id)->andReturns($transaction);
        }

        $this->app->instance(PaymentTransactionRepository::class, $transactionRepository);
    }

    /**
     * @param Collection<int, Payment> $payments
     * @param Collection<int, Transaction> $transactions
     */
    private function mockRefundServiceWithMultiplePaymentsAndTransactions(Collection $payments, Collection $transactions): void
    {
        $service = Mockery::mock(RefundElectronicPaymentService::class);

        $service->allows('refund')->times(count($payments))->andReturnUsing(function (MakePaymentRefundDto $dto) use ($transactions) {
            $this->assertInstanceOf(MakePaymentRefundDto::class, $dto);
            /** @var string $transactionId */
            $transactionId = $transactions->where('payment_id', $dto->originalPayment->id)->value('id');

            return new RefundPaymentResultDto(
                isSuccess: true,
                status: PaymentStatusEnum::CREDITED,
                refundPaymentId: Str::uuid()->toString(),
                transactionId: $transactionId,
            );
        });

        $this->app->instance(RefundElectronicPaymentService::class, $service);
    }
}
