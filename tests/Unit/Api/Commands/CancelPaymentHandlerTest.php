<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CancelPaymentHandler;
use App\Api\Exceptions\InvalidPaymentStateException;
use App\Api\Exceptions\PaymentCancellationFailedException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Unit\UnitTestCase;

class CancelPaymentHandlerTest extends UnitTestCase
{
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;
    /** @var MockObject&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
    }

    #[Test]
    public function handle_method_throws_not_found_exceptions_for_not_found_payment(): void
    {
        $this->paymentRepository->method('find')
            ->willThrowException(new ResourceNotFoundException('Payment was not found'));

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Payment was not found');

        $this->handler()->handle(Str::uuid()->toString());
    }

    #[Test]
    #[DataProvider('invalidPaymentStateProvider')]
    public function handle_method_throws_exception_for_invalid_payment_state(
        PaymentStatusEnum $status,
        PaymentGatewayEnum $gateway,
        Carbon $processedAt,
        string $expectedMessage
    ): void {
        $this->paymentRepository->method('find')->willReturn(Payment::factory()->withoutRelationships()->make(
            attributes: [
                'payment_status_id' => $status->value,
                'payment_gateway_id' => $gateway->value,
                'processed_at' => $processedAt,
            ]
        ));

        $this->expectException(PaymentCancellationFailedException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->handler()->handle(Str::uuid()->toString());
    }

    #[Test]
    public function handle_method_throws_exception_if_original_payment_method_was_not_found(): void
    {
        $this->paymentRepository->method('find')->willReturn(Payment::factory()->withoutRelationships()->make(attributes: [
            'payment_status_id' => PaymentStatusEnum::AUTHORIZED->value,
            'payment_gateway_id' => PaymentGatewayEnum::realGateways()[array_rand(PaymentGatewayEnum::realGateways())]->value,
            'processed_at' => Carbon::tomorrow(),
        ]));

        $this->expectException(InvalidPaymentStateException::class);
        $this->expectExceptionMessage(__('messages.operation.cancel.original_payment_method_not_found'));

        $this->handler()->handle(Str::uuid()->toString());
    }

    #[Test]
    public function handle_method_throws_exception_when_gateway_returns_error(): void
    {
        $this->paymentRepository->method('find')->willReturn($this->makePayment());
        $this->paymentRepository->method('transactionForOperation')->willReturn(Transaction::factory()->withoutRelationships()->make());

        $this->paymentProcessor->method('cancel')->willReturn(false);
        $this->paymentProcessor->method('getError')->willReturn('Something went wrong');

        $this->expectException(PaymentCancellationFailedException::class);
        $this->expectExceptionMessage('Something went wrong');

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler()->handle(Str::uuid()->toString());
    }

    #[Test]
    public function handle_method_throws_exception_if_original_transaction_is_not_found(): void
    {
        $this->paymentRepository->method('find')->willReturn($this->makePayment());
        $this->paymentRepository->method('transactionForOperation')->willReturn(null);

        $this->expectException(PaymentCancellationFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.cancel.missing_original_transaction'));

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler()->handle(Str::uuid()->toString());
    }

    #[Test]
    public function handle_method_successfully_cancels_payment(): void
    {
        $payment = $this->makePayment();
        $transaction = Transaction::factory()->withoutRelationships()->make();
        $this->paymentRepository->method('find')->willReturn($payment);
        $this->paymentRepository->method('transactionForOperation')->willReturn($transaction);

        $this->paymentProcessor->method('cancel')->willReturn(true);
        $this->paymentRepository->expects($this->exactly(2))->method('updateStatus');

        $this->paymentProcessor->expects($this->once())->method('setGateway');
        $this->paymentProcessor->expects($this->once())
            ->method('populate')
            ->with([
                OperationFields::REFERENCE_TRANSACTION_ID->value => $transaction->gateway_transaction_id,
                OperationFields::REFERENCE_ID->value => $payment->id,
                OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($payment->type->id),
                OperationFields::AMOUNT->value => new Money(
                    amount: $payment->amount,
                    currency: new Currency($payment->currency_code)
                ),
            ]);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $result = $this->handler()->handle(Str::uuid()->toString());

        $this->assertTrue($result->isSuccess);
    }

    public static function invalidPaymentStateProvider(): iterable
    {
        yield 'invalid payment status' => [
            'status' => PaymentStatusEnum::DECLINED,
            'gateway' => PaymentGatewayEnum::realGateways()[array_rand(PaymentGatewayEnum::realGateways())],
            'processedAt' => Carbon::yesterday(),
            'expectedMessage' => static fn () => __('messages.operation.cancel.payment_invalid_status'),
        ];

        yield 'payment gateway is not allowed for cancellation' => [
            'status' => PaymentStatusEnum::CAPTURED,
            'gateway' => PaymentGatewayEnum::CHECK,
            'processedAt' => Carbon::tomorrow(),
            'expectedMessage' => static fn () => __('messages.operation.cancel.cancellation_cannot_be_processed_for_gateway'),
        ];

        yield 'payment was already processed in gateway' => [
            'status' => PaymentStatusEnum::CAPTURED,
            'gateway' => PaymentGatewayEnum::realGateways()[array_rand(PaymentGatewayEnum::realGateways())],
            'processedAt' => Carbon::yesterday(),
            'expectedMessage' => static fn () => __('messages.operation.cancel.already_fully_processed_in_gateway'),
        ];
    }

    private function makePayment(PaymentStatusEnum $status = PaymentStatusEnum::AUTHORIZED): Payment
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $paymentType = PaymentType::factory()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account, 'type' => $paymentType]);
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_status_id' => $status->value,
                'payment_gateway_id' => PaymentGatewayEnum::realGateways()[array_rand(PaymentGatewayEnum::realGateways())]->value,
                'processed_at' => Carbon::tomorrow(),
            ],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'type' => $paymentType,
            ]
        );

        return $payment;
    }

    private function handler(): CancelPaymentHandler
    {
        $credentialsRepository = $this->createMock(originalClassName: CredentialsRepository::class);
        $credentialsRepository->method('get')->willReturn(WorldpayCredentialsStub::make());

        $this->app->instance(abstract: CredentialsRepository::class, instance: $credentialsRepository);

        return new CancelPaymentHandler(
            paymentRepository: $this->paymentRepository,
            paymentProcessor: $this->paymentProcessor,
        );
    }
}
