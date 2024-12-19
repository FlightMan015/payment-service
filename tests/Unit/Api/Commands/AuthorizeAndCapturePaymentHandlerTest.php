<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\AuthorizeAndCapturePaymentCommand;
use App\Api\Commands\AuthorizeAndCapturePaymentHandler;
use App\Api\DTO\AuthorizePaymentResultDto;
use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException as ApiResourceNotFoundException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentAttemptedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\FakeEventDispatcherTrait;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class AuthorizeAndCapturePaymentHandlerTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;
    use FakeEventDispatcherTrait;

    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;
    /** @var MockObject&PaymentProcessorRepository $paymentProcessorRepository */
    private PaymentProcessorRepository $paymentProcessorRepository;
    /** @var MockObject&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    private AuthorizeAndCapturePaymentHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);
        $this->paymentProcessorRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);

        $this->handler = new AuthorizeAndCapturePaymentHandler(
            paymentMethodRepository: $this->paymentMethodRepository,
            paymentRepository: $this->paymentRepository,
            paymentProcessorRepository: $this->paymentProcessorRepository,
            paymentProcessor: $this->paymentProcessor
        );

        $this->mockWorldPayCredentialsRepository();

        $this->fakeEvents();

        Payment::creating(static fn () => false);
    }

    #[Test]
    public function it_dispatches_event_and_returns_dto_for_success_operation_result(): void
    {
        $account = Account::factory()->makeWithRelationships(
            relationships: ['area' => Area::factory()->make()]
        );

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['account_id' => $account->id, 'gateway_id' => PaymentGatewayEnum::WORLDPAY->value],
            relationships: ['account' => $account]
        );

        $payment = Payment::factory()->withoutRelationships()->make(attributes: [
            'payment_status_id' => PaymentStatusEnum::AUTH_CAPTURING->value
        ]);
        $expectedTransactionId = Str::uuid()->toString();

        $command = new AuthorizeAndCapturePaymentCommand(
            amount: 12345,
            accountId: Str::uuid()->toString(),
            paymentMethodId: null,
            notes: 'some notes',
        );

        $this->paymentProcessorRepository->method('authorizeAndCapture')->willReturn(
            value: new PaymentProcessorResultDto(
                isSuccess: true,
                transactionId: $expectedTransactionId,
                message: null,
            )
        );

        $this->paymentMethodRepository->method('findPrimaryForAccount')->willReturn($paymentMethod);

        $this->paymentRepository->method('create')->willReturn($payment);
        $payment->payment_status_id = PaymentStatusEnum::CAPTURED->value;
        $this->paymentRepository->method('update')->willReturn($payment);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->assertEquals(
            new AuthorizePaymentResultDto(
                status: PaymentStatusEnum::CAPTURED,
                paymentId: $payment->id,
                transactionId: $expectedTransactionId,
                message: null,
            ),
            $this->handler->handle(command: $command)
        );

        Event::assertDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_returns_dto_with_error_message_when_payment_processor_returns_unsuccessful_and_has_an_exception(): void
    {
        $account = Account::factory()->makeWithRelationships(
            relationships: ['area' => Area::factory()->make()]
        );
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            relationships: ['account' => $account]
        );
        $payment = Payment::factory()->withoutRelationships()->make(attributes: [
            'id' => Str::uuid()->toString(),
            'payment_method_id' => $paymentMethod,
            'payment_status_id' => PaymentStatusEnum::AUTH_CAPTURING->value,
        ]);

        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->paymentProcessorRepository->method('authorizeAndCapture')->willReturn(
            value: new PaymentProcessorResultDto(isSuccess: false, message: __('messages.operation.something_went_wrong'))
        );

        $this->paymentRepository->method('create')->willReturn($payment);
        $payment->payment_status_id = PaymentStatusEnum::DECLINED->value;
        $this->paymentRepository->method('update')->willReturn($payment);

        $command = $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString());

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $result = $this->handler->handle(command: $command);

        $this->assertSame(PaymentStatusEnum::DECLINED, $result->status);
        $this->assertSame(__('messages.operation.something_went_wrong'), $result->message);
        Event::assertDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_account_does_not_have_a_primary_method(): void
    {
        $this->expectException(PaymentValidationException::class);
        $this->expectExceptionMessage('Invalid input');

        $this->handler->handle(command: $this->buildCommand(accountId: Str::uuid()->toString()));
    }

    #[Test]
    public function it_throws_exception_when_payment_method_is_not_found(): void
    {
        $this->paymentMethodRepository->method('find')->willThrowException(new ApiResourceNotFoundException());

        $this->expectException(PaymentValidationException::class);
        $this->expectExceptionMessage(__('messages.invalid_input'));

        $this->handler->handle(command: $this->buildCommand(accountId: Str::uuid()->toString(), paymentMethodId: Str::uuid()->toString()));
    }

    #[Test]
    public function it_throws_exception_when_payment_method_does_not_belong_to_the_account(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            relationships: ['account' => $account]
        );
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->expectException(PaymentValidationException::class);
        $this->expectExceptionMessage(__('messages.invalid_input'));

        $this->handler->handle(command: $this->buildCommand(accountId: Str::uuid()->toString(), paymentMethodId: Str::uuid()->toString()));
    }

    #[Test]
    public function it_throws_exception_when_payment_gateway_is_not_supported(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => count(PaymentGatewayEnum::cases()) + 10],
            relationships: ['account' => $account]
        );
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->expectException(UnsupportedValueException::class);
        $this->expectExceptionMessage(__('messages.gateway.not_implemented'));

        $this->handler->handle(command: $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString()));
    }

    #[Test]
    public function it_throws_exception_when_create_database_failed(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->paymentProcessorRepository->method('authorizeAndCapture')->willReturn(
            value: new PaymentProcessorResultDto(isSuccess: true)
        );

        $this->paymentRepository->method('create')->willThrowException(new ConnectionException(message: 'Connection issue'));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection issue');

        $command = $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString());

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler->handle(command: $command);

        Event::assertDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_operation_validation_failed(): void
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(
            relationships: ['area' => $area]
        );
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['name_on_account' => null],
            relationships: ['account' => $account]
        );
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->paymentProcessorRepository->method('authorizeAndCapture')->willThrowException(new OperationValidationException(errors: ['name_on_account missing']));

        $this->expectException(PaymentProcessingValidationException::class);
        $this->expectExceptionMessage(__('messages.payment.process_validation_error', ['message' => 'name_on_account missing']));

        $command = $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString());

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler->handle(command: $command);
    }

    private function buildCommand(
        string|null $accountId,
        string|null $paymentMethodId = null,
    ): AuthorizeAndCapturePaymentCommand {
        $accountId ??= Str::uuid()->toString();

        return new AuthorizeAndCapturePaymentCommand(
            amount: 12345,
            accountId: $accountId,
            paymentMethodId: $paymentMethodId,
            notes: 'some notes',
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->paymentMethodRepository,
            $this->paymentRepository,
            $this->paymentProcessorRepository,
            $this->paymentProcessor,
            $this->handler
        );
    }
}
