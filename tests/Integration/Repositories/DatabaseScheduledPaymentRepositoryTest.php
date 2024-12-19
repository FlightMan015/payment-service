<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\DatabaseScheduledPaymentRepository;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseScheduledPaymentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private DatabaseScheduledPaymentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app()->make(DatabaseScheduledPaymentRepository::class);
    }

    #[Test]
    public function find_throws_not_found_exception_for_not_existing_entity(): void
    {
        $scheduledPaymentId = Str::uuid()->toString();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.scheduled_payment.not_found', ['id' => $scheduledPaymentId]));
        $this->repository->find(scheduledPaymentId: $scheduledPaymentId);
    }

    #[Test]
    public function find_return_correct_scheduled_payment(): void
    {
        $scheduledPayment = ScheduledPayment::factory()->create(attributes: [
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'payment_id' => null
        ]);

        $columns = [
            'id',
            'status_id',
            'amount',
            'created_at',
        ];
        $actual = $this->repository->find(scheduledPaymentId: $scheduledPayment->id, columns: $columns);

        $this->assertInstanceOf(ScheduledPayment::class, $actual);
        $this->assertNotTrue(array_diff($columns, array_keys($actual->toArray())));
    }

    #[Test]
    public function it_updates_corresponding_fields(): void
    {
        $scheduledPayment = ScheduledPayment::factory()->create(attributes: [
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'payment_id' => null
        ]);

        $payment = Payment::factory()->create();
        $data = [
            'status_id' => ScheduledPaymentStatusEnum::SUBMITTED->value,
            'payment_id' => $payment->id,
        ];

        $actual = $this->repository->update(payment: $scheduledPayment, attributes: $data);

        $this->assertInstanceOf(ScheduledPayment::class, $actual);
        $this->assertEquals($actual->status_id, ScheduledPaymentStatusEnum::SUBMITTED->value);
        $this->assertEquals($actual->payment_id, $payment->id);
    }

    #[Test]
    public function it_returns_pending_scheduled_payments_for_specific_area_as_expected(): void
    {
        $expectedPendingScheduledPaymentsCount = 7;

        $area = Area::factory()->create();
        $account = Account::factory()->for($area)->create();
        $paymentMethod = PaymentMethod::factory()->for($account)->create();

        ScheduledPayment::factory()
            ->for($account)
            ->for($paymentMethod)
            ->count($expectedPendingScheduledPaymentsCount)
            ->create(attributes: ['status_id' => ScheduledPaymentStatusEnum::PENDING->value, 'payment_id' => null]);

        // Create some scheduled payments with different status
        ScheduledPayment::factory()
            ->for($account)
            ->for($paymentMethod)
            ->count(3)
            ->create(attributes: ['status_id' => ScheduledPaymentStatusEnum::SUBMITTED->value, 'payment_id' => null]);

        // Create some scheduled payments with different area
        $differentArea = Area::factory()->create();
        $differentAccount = Account::factory()->for($differentArea)->create();
        $differentPaymentMethod = PaymentMethod::factory()->for($differentAccount)->create();
        ScheduledPayment::factory()
            ->for($differentAccount)
            ->for($differentPaymentMethod)
            ->count(2)
            ->create(attributes: ['status_id' => ScheduledPaymentStatusEnum::PENDING->value, 'payment_id' => null]);

        $paginator = (new DatabaseScheduledPaymentRepository())->getPendingScheduledPaymentsForArea(
            areaId: $area->id,
            page: 1,
            quantity: 5
        );

        $paginator->getCollection()->each(callback: function (ScheduledPayment $scheduledPayment) {
            $this->assertSame(
                expected: ScheduledPaymentStatusEnum::PENDING->value,
                actual: $scheduledPayment->status_id
            );
        });

        $this->assertSame($expectedPendingScheduledPaymentsCount, $paginator->total());
    }

    #[Test]
    public function it_returns_duplicate_scheduled_payment_by_given_parameters(): void
    {
        $area = Area::factory()->create();
        $account = Account::factory()->for($area)->create();
        $paymentMethod = PaymentMethod::factory()->for($account)->create();

        $scheduledPayment = ScheduledPayment::factory()->for($account)->for($paymentMethod)->create(
            [
                'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'metadata' => ['subscription_id' => Str::uuid()->toString()],
                'payment_id' => null,
            ]
        );

        $result = (new DatabaseScheduledPaymentRepository())->findDuplicate(
            accountId: $account->id,
            paymentMethodId: $paymentMethod->id,
            trigger: ScheduledPaymentTriggerEnum::from($scheduledPayment->trigger_id),
            amount: $scheduledPayment->amount,
            metadata: (array)$scheduledPayment->metadata
        );

        $this->assertInstanceOf(ScheduledPayment::class, $result);
    }
}
