<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\ValidatePaymentMethodCommand;
use App\Api\Commands\ValidatePaymentMethodHandler;
use App\Api\DTO\ValidationOperationResultDto;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentAttemptedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\FakeEventDispatcherTrait;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class ValidatePaymentMethodHandlerTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;
    use FakeEventDispatcherTrait;

    private MockObject|PaymentMethodRepository $paymentMethodRepository;
    private MockObject|PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWorldPayCredentialsRepository();
        $this->fakeEvents();
    }

    #[Test]
    public function handle_method_returns_dto_with_is_valid_true_if_the_gateway_returns_true_for_authorization(): void
    {
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: [
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'id' => Str::uuid()->toString(),
                'payment_type_id' => PaymentTypeEnum::CC->value,
            ],
            relationships: [
                'account' => Account::factory()->makeWithRelationships(relationships: [
                    'area' => Area::factory()->make()
                ]),
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::CC->value
                ])
            ]
        );
        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());
        $this->mockPaymentMethodRepository(paymentMethod: $paymentMethod);

        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type_id' => $paymentMethod->payment_type_id,
                'payment_status_id' => PaymentStatusEnum::AUTHORIZING,
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );
        $this->mockPaymentRepository(
            payment: $payment,
        );

        $command = new ValidatePaymentMethodCommand(paymentMethod: $paymentMethod);

        /** @var MockObject|PaymentProcessor $paymentProcessor */
        $paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $paymentProcessor->method('authorize')->willReturn(value: true);

        $this->assertEquals(
            expected: new ValidationOperationResultDto(isValid: true),
            actual: $this->handler(paymentProcessor: $paymentProcessor)->handle(command: $command)
        );

        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    public function handle_method_returns_dto_with_error_message_if_the_gateway_returns_false_for_authorization(): void
    {
        $paymentMethod = PaymentMethod::factory()->cc()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: ['account' => Account::factory()->makeWithRelationships(relationships: [
                'area' => Area::factory()->make(),
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::CC->value
                ])
            ])]
        );
        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());
        $this->mockPaymentMethodRepository(paymentMethod: $paymentMethod);

        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type_id' => $paymentMethod->payment_type_id,
                'payment_status_id' => PaymentStatusEnum::AUTHORIZING,
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );
        $this->mockPaymentRepository(
            payment: $payment,
        );
        $command = new ValidatePaymentMethodCommand(paymentMethod: $paymentMethod);

        /** @var MockObject|PaymentProcessor $paymentProcessor */
        $paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $paymentProcessor->method('authorize')->willReturn(value: false);
        $paymentProcessor->method('getError')->willReturn(value: 'Something went wrong');

        $this->handler(paymentProcessor: $paymentProcessor)->handle(command: $command);

        $this->assertEquals(
            expected: new ValidationOperationResultDto(isValid: false, errorMessage: 'Something went wrong'),
            actual: $this->handler(paymentProcessor: $paymentProcessor)->handle(command: $command)
        );

        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    private function mockPaymentMethodRepository(PaymentMethod $paymentMethod): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);
        $this->paymentMethodRepository->method('save')->willReturn($paymentMethod);
    }

    private function mockPaymentRepository(Payment $payment): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->paymentRepository->method('create')->willReturn($payment);
        $this->paymentRepository->method('update')->willReturn($payment);
    }

    private function handler(PaymentProcessor $paymentProcessor): ValidatePaymentMethodHandler
    {
        return new ValidatePaymentMethodHandler(
            paymentProcessor: $paymentProcessor,
            paymentMethodRepository: $this->paymentMethodRepository,
            paymentRepository: $this->paymentRepository
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentMethodRepository, $this->paymentRepository);
    }
}
