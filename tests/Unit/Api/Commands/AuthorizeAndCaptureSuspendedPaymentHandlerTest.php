<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\AuthorizeAndCapturePaymentCommand;
use App\Api\Commands\AuthorizeAndCaptureSuspendedPaymentHandler;
use App\Api\DTO\AuthorizePaymentResultDto;
use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\SuspendedPaymentProcessedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class AuthorizeAndCaptureSuspendedPaymentHandlerTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;
    /** @var MockObject&PaymentProcessorRepository $paymentProcessorRepository */
    private PaymentProcessorRepository $paymentProcessorRepository;
    /** @var MockObject&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    private AuthorizeAndCaptureSuspendedPaymentHandler $handler;
    private Area $area;
    private Account $account;
    private Payment $suspendedPayment;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);
        $this->paymentProcessorRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);

        $this->handler = new AuthorizeAndCaptureSuspendedPaymentHandler(
            paymentMethodRepository: $this->paymentMethodRepository,
            paymentRepository: $this->paymentRepository,
            paymentProcessorRepository: $this->paymentProcessorRepository,
            paymentProcessor: $this->paymentProcessor
        );

        $this->area = Area::factory()->make(['external_ref_id' => 39]);
        $account = Account::factory()->makeWithRelationships(
            attributes: [
                'area_id' => $this->area->id,
            ],
            relationships: ['area' => $this->area]
        );
        $this->account = $account;
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            relationships: [
                'account' => $this->account
            ]
        );

        $suspendedPayment = Payment::factory()
            ->makeWithRelationships(
                attributes: [
                    'account_id' => $this->account->id,
                    'payment_status_id' => PaymentStatusEnum::SUSPENDED->value,
                    'payment_method_id' => $paymentMethod->id
                ],
                relationships: [
                    'paymentMethod' => $paymentMethod,
                    'account' => $this->account,
                ]
            );
        $this->suspendedPayment = $suspendedPayment;

        $this->mockWorldPayCredentialsRepository();
    }

    #[Test]
    public function it_authorizes_and_captures_suspended_payment(): void
    {
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $this->account]);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);
        $this->paymentRepository
            ->method('find')
            ->with($this->suspendedPayment->id, ['*'], ['paymentMethod', 'status'])
            ->willReturn($this->suspendedPayment);

        $capturedPayment = Payment::factory()->makeWithRelationships(
            attributes: [
                'id' => $this->suspendedPayment->id,
                'account_id' => $this->account->id,
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            ],
            relationships: [
                'paymentMethod' => $paymentMethod,
            ]
        );
        $this->suspendedPayment->setRelation('originalPayment', $capturedPayment);

        $this->paymentRepository
            ->expects($this->once())
            ->method('update')
            ->with($this->suspendedPayment, ['payment_status_id' => PaymentStatusEnum::CAPTURED->value])
            ->willReturn($capturedPayment);

        $transId = Str::uuid()->toString();

        $this->paymentProcessorRepository
            ->method('authorizeAndCapture')
            ->willReturn(new PaymentProcessorResultDto(
                isSuccess: true,
                transactionId: $transId,
            ));

        Log::shouldReceive('info')
            ->once()
            ->with(__('messages.payment.suspended_payments_processing.capturing'), ['paymentId' => $this->suspendedPayment->id]);
        Log::shouldReceive('info')
            ->once()
            ->with(__('messages.payment.suspended_payments_processing.captured'), ['paymentId' => $this->suspendedPayment->id]);
        Log::shouldReceive('info')
            ->once()
            ->with(__('messages.payment.suspended_payments_processing.updated'), [
                'paymentId' => $this->suspendedPayment->id,
                'status' => PaymentStatusEnum::CAPTURED->value
            ]);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $result = $this->handler->handle($this->buildCommand($this->suspendedPayment));

        Event::assertDispatched(event: SuspendedPaymentProcessedEvent::class);

        $this->assertInstanceOf(AuthorizePaymentResultDto::class, $result);
        $this->assertEquals($this->suspendedPayment->id, $result->paymentId);
        $this->assertEquals(PaymentStatusEnum::from($this->suspendedPayment->payment_status_id), $result->status);
        $this->assertEquals($result->transactionId, $transId);
    }

    #[Test]
    public function it_throws_exception_if_suspended_payment_id_does_not_exist(): void
    {
        $paymentId = Str::uuid()->toString();
        $this
            ->paymentRepository
            ->method('find')
            ->willThrowException(new ResourceNotFoundException(__('messages.payment.not_found', ['id' => $paymentId])));

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment.not_found', ['id' => $paymentId]));

        $this->handler->handle(new AuthorizeAndCapturePaymentCommand(
            amount: $this->suspendedPayment->amount,
            accountId: $this->suspendedPayment->account_id,
            paymentMethodId: $this->suspendedPayment->payment_method_id,
            notes: $this->suspendedPayment->notes,
            paymentId: $paymentId,
        ));
    }

    #[Test]
    public function it_throws_exception_when_payment_is_not_a_suspended_payment(): void
    {
        $payment = Payment::factory()
            ->makeWithRelationships(
                attributes: [
                    'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                ],
                relationships: [
                    'account' => $this->account,
                    'paymentMethod' => PaymentMethod::factory()->makeWithRelationships(relationships: [
                        'account' => $this->account,
                    ])
                ]
            );

        $this->paymentRepository->method('find')->willReturn($payment);

        $this->expectExceptionObject(new PaymentValidationException(
            message: __('messages.payment.suspended_payments_processing.invalid_status'),
            errors: [__('messages.payment.suspended_payments_processing.not_suspended', ['id' => $payment->id])]
        ));

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler->handle($this->buildCommand($payment));
    }

    #[Test]
    public function it_throws_exception_when_account_does_not_have_a_primary_method(): void
    {
        $this->expectException(PaymentValidationException::class);
        $this->expectExceptionMessage(__('messages.invalid_input'));

        $this->suspendedPayment->payment_method_id = null;

        $this->paymentRepository
            ->method('find')
            ->willReturn($this->suspendedPayment);

        $this->handler->handle($this->buildCommand($this->suspendedPayment));
    }

    #[Test]
    public function it_throws_exception_when_payment_method_is_not_found(): void
    {
        $this->expectException(PaymentValidationException::class);
        $this->expectExceptionMessage(__('messages.invalid_input'));

        $this->paymentRepository
            ->method('find')
            ->willReturn($this->suspendedPayment);
        $this->paymentMethodRepository
            ->method('find')
            ->willThrowException(new ResourceNotFoundException());

        $this->handler->handle(command: $this->buildCommand($this->suspendedPayment));
    }

    #[Test]
    public function it_throws_exception_when_payment_method_does_not_belong_to_the_account(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);

        $this->paymentMethodRepository
            ->method('find')
            ->willReturn($paymentMethod);
        $this->paymentRepository
            ->method('find')
            ->willReturn($this->suspendedPayment);

        $this->expectException(PaymentValidationException::class);
        $this->expectExceptionMessage(__('messages.invalid_input'));

        $this->handler->handle(command: $this->buildCommand($this->suspendedPayment));
    }

    #[Test]
    public function it_throws_exception_when_payment_gateway_is_not_supported(): void
    {
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => count(PaymentGatewayEnum::cases()) + 10],
            relationships: ['account' => $this->account]
        );

        $this->paymentRepository->method('find')->willReturn($this->suspendedPayment);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->expectException(UnsupportedValueException::class);
        $this->expectExceptionMessage(__('messages.gateway.not_implemented'));

        $this->handler->handle(command: $this->buildCommand($this->suspendedPayment));
    }

    #[Test]
    public function it_throws_exception_when_operation_validation_fails(): void
    {
        $paymentMethod = PaymentMethod::factory()
            ->makeWithRelationships(attributes: ['name_on_account' => null], relationships: ['account' => $this->account]);
        $this->paymentRepository->method('find')->willReturn($this->suspendedPayment);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->paymentProcessorRepository
            ->method('authorizeAndCapture')
            ->willThrowException(new OperationValidationException(errors: ['name_on_account missing']));

        $this->expectException(PaymentProcessingValidationException::class);
        $this->expectExceptionMessage(__('messages.payment.process_validation_error', ['message' => 'name_on_account missing']));

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler->handle(command: $this->buildCommand($this->suspendedPayment));
    }

    private function buildCommand(Payment $payment): AuthorizeAndCapturePaymentCommand
    {
        return new AuthorizeAndCapturePaymentCommand(
            amount: $payment->amount,
            accountId: $payment->account_id,
            paymentMethodId: $payment->payment_method_id,
            notes: $payment->notes,
            paymentId: $payment->id,
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
