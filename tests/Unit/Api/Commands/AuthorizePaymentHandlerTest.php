<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\AuthorizePaymentCommand;
use App\Api\Commands\AuthorizePaymentHandler;
use App\Api\DTO\AuthorizePaymentResultDto;
use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException as ApiResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\CRM\AccountRepository;
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
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\FakeEventDispatcherTrait;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class AuthorizePaymentHandlerTest extends UnitTestCase
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
    /** @var MockObject&AccountRepository $accountRepository */
    private AccountRepository $accountRepository;
    private AuthorizePaymentHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);
        $this->paymentProcessorRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $this->accountRepository = $this->createMock(originalClassName: AccountRepository::class);

        $this->handler = new AuthorizePaymentHandler(
            paymentMethodRepository: $this->paymentMethodRepository,
            paymentRepository: $this->paymentRepository,
            paymentProcessorRepository: $this->paymentProcessorRepository,
            paymentProcessor: $this->paymentProcessor,
            accountRepository: $this->accountRepository
        );

        $this->mockWorldPayCredentialsRepository();

        $this->fakeEvents();
    }

    #[Test]
    public function it_returns_dto_for_success_operation_result(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value],
            relationships: ['account' => $account]
        );
        $payment = Payment::factory()->withoutRelationships()->make(attributes: [
            'id' => Str::uuid()->toString(),
            'payment_method_id' => $paymentMethod,
            'payment_status_id' => PaymentStatusEnum::AUTHORIZING->value,
        ]);
        $expectedTransactionId = Str::uuid()->toString();

        $command = new AuthorizePaymentCommand(
            amount: 12345,
            accountId: $account->id,
            paymentMethodId: null,
            notes: 'some notes',
        );

        $this->accountRepository->method('find')->willReturn(value: $account);

        $this->paymentProcessorRepository->method('authorize')->willReturn(
            value: new PaymentProcessorResultDto(
                isSuccess: true,
                transactionId: $expectedTransactionId,
                message: null,
            )
        );

        $this->paymentMethodRepository->method('findPrimaryForAccount')->willReturn($paymentMethod);

        $this->paymentRepository->method('create')->willReturn($payment);
        $payment->payment_status_id = PaymentStatusEnum::AUTHORIZED->value;
        $this->paymentRepository->method('update')->willReturn($payment);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->assertEquals(
            new AuthorizePaymentResultDto(
                status: PaymentStatusEnum::AUTHORIZED,
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
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->accountRepository->method('find')->willReturn($account);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->paymentProcessorRepository->method('authorize')->willReturn(
            value: new PaymentProcessorResultDto(isSuccess: false, message: __('messages.operation.something_went_wrong'))
        );

        $payment = Payment::factory()->withoutRelationships()->make(attributes: [
            'id' => Str::uuid()->toString(),
            'payment_method_id' => $paymentMethod,
            'payment_status_id' => PaymentStatusEnum::AUTHORIZING->value,
        ]);

        $this->paymentRepository->method('create')->willReturn($payment);
        $payment->payment_status_id = PaymentStatusEnum::DECLINED->value;
        $this->paymentRepository->method('update')->willReturn($payment);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $result = $this->handler->handle(command: $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString()));

        $this->assertSame(PaymentStatusEnum::DECLINED, $result->status);
        $this->assertSame(__('messages.operation.something_went_wrong'), $result->message);
        Event::assertDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_account_is_not_found_in_database(): void
    {
        $this->accountRepository->method('find')->willReturn(null);

        $this->expectException(exception: UnprocessableContentException::class);
        $this->expectExceptionMessage(message: __('messages.account.not_found'));

        $this->handler->handle(command: $this->buildCommand());

        Event::assertNotDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_account_does_not_have_a_primary_method(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $this->accountRepository->method('find')->willReturn($account);

        $this->expectException(exception: PaymentValidationException::class);
        $this->expectExceptionMessage(message: 'Invalid input');

        $this->handler->handle(command: $this->buildCommand(accountId: $account->id));

        Event::assertNotDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_payment_method_is_not_found(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $this->accountRepository->method('find')->willReturn($account);

        $this->paymentMethodRepository->method('find')->willThrowException(new ApiResourceNotFoundException());

        $this->expectException(exception: PaymentValidationException::class);
        $this->expectExceptionMessage(message: __('messages.invalid_input'));

        $this->handler->handle(command: $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString()));

        Event::assertDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_payment_method_does_not_belong_to_the_account(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $this->accountRepository->method('find')->willReturn($account);

        $anotherAccount = Account::factory()->withoutRelationships()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $anotherAccount]);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->expectException(exception: PaymentValidationException::class);
        $this->expectExceptionMessage(message: 'Invalid input');

        $this->handler->handle(command: $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString()));

        Event::assertNotDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_gateway_is_not_supported(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->accountRepository->method('find')->willReturn($account);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: [
                'payment_gateway_id' => array_sum(
                    array: array_map(
                        callback: static fn (PaymentGatewayEnum $gateway) => $gateway->value,
                        array: PaymentGatewayEnum::cases()
                    )
                ),
            ],
            relationships: ['account' => $account]
        );
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $expectedException = new UnsupportedValueException(message: __('messages.gateway.not_implemented'));
        $this->paymentProcessor->method('getException')->willReturn($expectedException);

        $this->expectExceptionObject(exception: $expectedException);

        $this->handler->handle(command: $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString()));
    }

    #[Test]
    public function it_throws_exception_when_operation_validation_fails(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->accountRepository->method('find')->willReturn($account);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['name_on_account' => null],
            relationships: ['account' => $account]
        );
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->paymentProcessorRepository->method('authorize')
            ->willThrowException(new OperationValidationException(errors: ['Name on account is required']));

        $this->expectException(PaymentProcessingValidationException::class);
        $this->expectExceptionMessage(__('messages.payment.process_validation_error', ['message' => 'Name on account is required']));

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler->handle(command: $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString()));
    }

    #[Test]
    public function it_throws_exception_when_credit_card_validation_fails(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->accountRepository->method('find')->willReturn($account);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['name_on_account' => null],
            relationships: ['account' => $account]
        );
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->paymentProcessorRepository->method('authorize')
            ->willThrowException(new CreditCardValidationException(message: __('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required')));

        $this->expectException(PaymentProcessingValidationException::class);
        $this->expectExceptionMessage(
            __(
                'messages.payment.process_validation_error',
                ['message' => __('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required')]
            )
        );

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler->handle(command: $this->buildCommand(accountId: $account->id, paymentMethodId: Str::uuid()->toString()));
    }

    private function buildCommand(string|null $accountId = null, string|null $paymentMethodId = null): AuthorizePaymentCommand
    {
        $accountId ??= Str::uuid()->toString();

        return new AuthorizePaymentCommand(
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
            $this->accountRepository,
            $this->handler
        );
    }
}
