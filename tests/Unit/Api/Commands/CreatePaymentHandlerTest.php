<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CreateCheckPaymentCommand;
use App\Api\Commands\CreatePaymentHandler;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class CreatePaymentHandlerTest extends UnitTestCase
{
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPaymentMethodRepositoryMock();
    }

    #[Test]
    public function it_returns_payment_id_in_the_end_of_the_process(): void
    {
        $data = [
            'account_id' => Str::uuid()->toString(),
            'amount' => 100,
            'type' => PaymentTypeEnum::CHECK->name,
            'check_date' => Carbon::create(2021, 01, 01),
            'notes' => 'some notes',
        ];
        $account = Account::factory()->withoutRelationships()->make();

        $expectedId = Str::uuid()->toString();
        $this->mockPaymentRepositoryExpectedResult(expectedId: $expectedId, account: $account);

        $id = $this->handler()->handle(CreateCheckPaymentCommand::create(
            accountId: $data['account_id'],
            amount: $data['amount'],
            type: $data['type'],
            checkDate: $data['check_date'],
            notes: $data['notes'],
        ));

        $this->assertSame($expectedId, $id);
    }

    private function createPaymentMethodRepositoryMock(): void
    {
        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);
    }

    private function handler(): CreatePaymentHandler
    {
        return new CreatePaymentHandler(paymentRepository: $this->paymentRepository);
    }

    private function mockPaymentRepositoryExpectedResult(
        string $expectedId,
        Account|null $account = null
    ): void {
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(attributes: [
            'payment_gateway_id' => PaymentGatewayEnum::CHECK->value,
            'id' => $expectedId,
            'payment_type_id' => PaymentTypeEnum::CHECK->value,
            'external_ref_id' => null,
        ], relationships: ['account' => $account]);

        $payment =  Payment::factory()->makeWithRelationships(attributes: [
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'id' => $expectedId,
        ], relationships: ['account' => $account, 'paymentMethod' => $paymentMethod]);
        $this->paymentRepository->method('create')->willReturn(value: $payment);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentRepository);
    }
}
