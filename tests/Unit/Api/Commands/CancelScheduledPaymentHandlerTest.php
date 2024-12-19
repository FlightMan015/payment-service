<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CancelScheduledPaymentHandler;
use App\Api\Exceptions\PaymentCancellationFailedException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class CancelScheduledPaymentHandlerTest extends UnitTestCase
{
    /** @var MockObject&ScheduledPaymentRepository $scheduledPaymentRepository */
    private ScheduledPaymentRepository $scheduledPaymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduledPaymentRepository = $this->createMock(originalClassName: ScheduledPaymentRepository::class);
    }

    #[Test]
    public function handle_method_throws_not_found_exceptions_for_not_found_payment(): void
    {
        $this->scheduledPaymentRepository->method('find')
            ->willThrowException(new ResourceNotFoundException('Scheduled Payment was not found'));

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Scheduled Payment was not found');

        $this->handler()->handle(Str::uuid()->toString());
    }

    #[Test]
    #[DataProvider('invalidPaymentStateProvider')]
    public function handle_method_throws_exception_for_invalid_payment_state(ScheduledPaymentStatusEnum $status, string $expectedMessage): void
    {
        $this->scheduledPaymentRepository->method('find')->willReturn(ScheduledPayment::factory()->withoutRelationships()->make(
            attributes: [
                'status_id' => $status->value,
            ]
        ));

        $this->expectException(PaymentCancellationFailedException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->handler()->handle(Str::uuid()->toString());
    }

    #[Test]
    public function handle_method_successfully_cancels_payment(): void
    {
        $scheduledPayment = ScheduledPayment::factory()->withoutRelationships()->make(
            attributes: [
                'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            ]
        );
        $this->scheduledPaymentRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($scheduledPayment);
        $this->scheduledPaymentRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                $scheduledPayment,
                ['status_id' => ScheduledPaymentStatusEnum::CANCELLED->value]
            )
            ->willReturn($scheduledPayment);

        $result = $this->handler()->handle(Str::uuid()->toString());

        $this->assertTrue($result->isSuccess);
    }

    public static function invalidPaymentStateProvider(): iterable
    {
        yield 'Payment was submitted to be proccessed in gateway' => [
            'status' => ScheduledPaymentStatusEnum::SUBMITTED,
            'expectedMessage' => static fn () => __('messages.scheduled_payment.invalid_status_for_cancellation'),
        ];
        yield 'Payment is cancelled already' => [
            'status' => ScheduledPaymentStatusEnum::CANCELLED,
            'expectedMessage' => static fn () => __('messages.scheduled_payment.invalid_status_for_cancellation'),
        ];
    }

    private function handler(): CancelScheduledPaymentHandler
    {
        return new CancelScheduledPaymentHandler(
            scheduledPaymentRepository: $this->scheduledPaymentRepository,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->scheduledPaymentRepository);
    }
}
