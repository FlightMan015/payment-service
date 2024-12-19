<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\RefundPaymentFailedEvent;
use App\Helpers\MoneyHelper;
use App\Listeners\CollectRefundPaymentFailedMetricsListener;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\DeclineReason;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class CollectRefundPaymentFailedMetricsListenerTest extends UnitTestCase
{
    private CollectRefundPaymentFailedMetricsListener $listener;
    /** @var InfluxClient&MockInterface $mockInfluxClient */
    private InfluxClient $mockInfluxClient;
    /** @var WriteApi&MockInterface $mockWriteApi */
    private WriteApi $mockWriteApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupInfluxClient();
        $this->setupListener();

        Event::fake();
    }

    #[Test]
    public function it_is_listening_to_the_refund_payment_failed_event(): void
    {
        Event::assertListening(
            expectedEvent: RefundPaymentFailedEvent::class,
            expectedListener: CollectRefundPaymentFailedMetricsListener::class
        );
    }

    #[Test]
    public function it_is_listening_on_the_correct_queue(): void
    {
        $expectedQueue = config('queue.connections.sqs.queues.collect_metrics');

        $queue = $this->listener->viaQueue();

        $this->assertEquals($expectedQueue, $queue);
    }

    #[Test]
    public function it_logs_start_process_action_stores_the_metric_in_influxdb(): void
    {
        $event = $this->setupEvent();

        Log::shouldReceive('info')->once()->with(
            'Collect Refund Payment Failed Metrics Job Started',
            ['job_id' => $this->listener->job?->uuid()]
        );

        $transaction = $event->refund->transactions->first();

        $expectedPoint = new Point('refund-payments-failed');
        $expectedPoint
            ->addField('original_amount', MoneyHelper::convertToDecimal($event->refund->originalPayment->amount))
            ->addField('refund_amount', MoneyHelper::convertToDecimal($event->refund->amount))
            ->addField('customer_external_reference_id', $event->refund->account->external_ref_id)
            ->addField('customer_account_id', $event->refund->account_id)
            ->addField('payment_method_id', $event->refund->paymentMethod?->id)
            ->addField('payment_method_type', $event->refund->paymentMethod?->type->name)
            ->addField('refund_payment_id', $event->refund->id)
            ->addField('original_payment_id', $event->refund->originalPayment->id)
            ->addField('area_id', $event->refund->account->area->id)
            ->addField('office_id', $event->refund->account->area->external_ref_id)
            ->addTag('office', $event->refund->account->area->name)
            ->time($event->timestamp);

        if (!is_null($transaction) && !is_null($transaction->declineReason)) {
            $expectedPoint->addTag('fail_reason', $transaction->declineReason->name);
        }

        $this->mockWriteApi->expects('write')->with($this->equalTo($expectedPoint));

        $this->mockWriteApi->expects('close');

        $this->listener->handle($event);
    }

    #[Test]
    public function it_logs_an_error_when_an_exception_is_thrown(): void
    {
        $event = $this->setupEvent();

        Log::shouldReceive('error')->once();

        $this->mockWriteApi->expects('close');
        $this->listener->failed($event, new \Exception('Some Error'));
    }

    private function setupEvent(): RefundPaymentFailedEvent
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString()],
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make(),
            ]
        );
        $originalPayment = Payment::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString(), 'payment_status_id' => PaymentStatusEnum::CAPTURED->value],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
            ]
        );
        $transaction = Transaction::factory()->makeWithRelationships(
            attributes: ['transaction_type_id' => TransactionTypeEnum::CREDIT->value],
            relationships: [
                'declineReason' => DeclineReason::factory()->make(['name' => 'Test'])
            ]
        );

        $refundPayment = Payment::factory()->makeWithRelationships(
            attributes: ['id' => Str::uuid()->toString(), 'payment_status_id' => PaymentStatusEnum::CREDITED->value],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'originalPayment' => $originalPayment,
                'transactions' => $transaction,
            ]
        );
        /** @var Payment&MockInterface $refundPayment */
        $refundPayment = Mockery::mock($refundPayment)->makePartial();
        $refundPayment->shouldReceive('transactionForOperation')
            ->withArgs([OperationEnum::CREDIT])
            ->andReturn($transaction);

        return new RefundPaymentFailedEvent(refund: $refundPayment);
    }

    private function setupInfluxClient(): void
    {
        $this->mockInfluxClient = Mockery::mock(InfluxClient::class);
        $this->mockWriteApi = Mockery::mock(WriteApi::class);
    }

    private function setupListener(): void
    {
        $this->mockInfluxClient->expects('createWriteApi')->andReturns($this->mockWriteApi);
        $this->listener = new CollectRefundPaymentFailedMetricsListener(influxClient: $this->mockInfluxClient);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->listener, $this->mockInfluxClient, $this->mockWriteApi);
    }
}
