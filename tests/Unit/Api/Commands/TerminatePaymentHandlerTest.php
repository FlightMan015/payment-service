<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\TerminatePaymentHandler;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentTerminatedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Str;
use Tests\Unit\UnitTestCase;

class TerminatePaymentHandlerTest extends UnitTestCase
{
    protected MockObject&PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->paymentRepository = $this->createMock(PaymentRepository::class);
    }

    #[Test]
    public function it_terminates_payment_when_payment_exists_and_not_already_terminated(): void
    {
        $paymentId = Str::uuid()->toString();
        $payment = Payment::factory()->withoutRelationships()->make(['id' => $paymentId, 'payment_status_id' => PaymentStatusEnum::SUSPENDED->value]);

        Carbon::setTestNow(now());

        $urn = 'urn:aptive:organization:users:0a5d1d26-70de-3e5a-aef3-128f9940d029';
        $terminatePaymentHandler = Mockery::mock(TerminatePaymentHandler::class, [$this->paymentRepository])->makePartial();
        $terminatePaymentHandler->shouldAllowMockingProtectedMethods();
        $terminatePaymentHandler
            ->shouldReceive('buildUserUrn')
            ->andReturn($urn);

        $this
            ->paymentRepository
            ->expects($this->once())
            ->method('find')
            ->with($paymentId)
            ->willReturn($payment);

        $this
            ->paymentRepository
            ->expects($this->once())
            ->method('update')
            ->with($payment, [
                'payment_status_id' => PaymentStatusEnum::TERMINATED->value,
                'terminated_at' => now(),
                'terminated_by' => $urn,
            ])
            ->willReturn(Payment::factory()->makeWithRelationships(
                attributes: [
                    'id' => $paymentId,
                    'payment_status_id' => PaymentStatusEnum::TERMINATED->value,
                    'terminated_at' => now()
                ],
                relationships: [
                    'account' => Account::factory()->withoutRelationships()->make(),
                    'paymentMethod' => PaymentMethod::factory()->withoutRelationships()->make(),
                    'originalPayment' => Payment::factory()->withoutRelationships()->make(),
                ]
            ));

        $result = $terminatePaymentHandler->handle($paymentId);

        Event::assertDispatched(PaymentTerminatedEvent::class, static fn (PaymentTerminatedEvent $event) => $event->terminatedPayment->id === $result->id);

        $this->assertEquals(PaymentStatusEnum::TERMINATED->value, $result->payment_status_id);
        $this->assertEquals($paymentId, $result->id);
    }

    #[Test]
    public function it_throws_exception_when_payment_cant_be_found(): void
    {
        $paymentId = Str::uuid()->toString();

        $this
            ->paymentRepository
            ->expects($this->once())
            ->method('find')
            ->with($paymentId)
            ->willThrowException(new ResourceNotFoundException());

        $this
            ->paymentRepository
            ->expects($this->never())
            ->method('update');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler()->handle($paymentId);
    }

    #[Test]
    public function it_throws_exception_when_payment_already_terminated(): void
    {
        $paymentId = Str::uuid()->toString();
        $payment = Payment::factory()->withoutRelationships()->make(['id' => $paymentId, 'payment_status_id' => PaymentStatusEnum::TERMINATED->value]);

        $this
            ->paymentRepository
            ->expects($this->once())
            ->method('find')
            ->with($paymentId)
            ->willReturn($payment);

        $this
            ->paymentRepository
            ->expects($this->never())
            ->method('update');

        $this->expectException(UnprocessableContentException::class);
        $this->expectExceptionMessage(__('messages.payment.already_terminated', ['id' => $paymentId]));

        $this->handler()->handle($paymentId);
    }

    #[Test]
    public function it_throws_exception_when_payment_is_not_a_suspended_payment(): void
    {
        $paymentId = Str::uuid()->toString();
        $payment = Payment::factory()->withoutRelationships()->make(['id' => $paymentId, 'payment_status_id' => PaymentStatusEnum::CREDITED->value]);

        $this
            ->paymentRepository
            ->expects($this->once())
            ->method('find')
            ->with($paymentId)
            ->willReturn($payment);

        $this
            ->paymentRepository
            ->expects($this->never())
            ->method('update');

        $this->expectException(UnprocessableContentException::class);

        $this->handler()->handle($paymentId);
    }

    protected function handler(): TerminatePaymentHandler
    {
        return new TerminatePaymentHandler($this->paymentRepository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentRepository);
    }
}
