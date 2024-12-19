<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\PaymentSyncReportHandler;
use App\Api\DTO\PaymentSyncReportDto;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Helpers\SlackMessageBuilder;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\Unit\UnitTestCase;

class PaymentSyncReportHandlerTest extends UnitTestCase
{
    protected MockObject&PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = $this->createMock(PaymentRepository::class);
    }

    #[Test]
    public function it_only_sends_no_payment_exists_message_and_does_not_mention_channel_when_no_unsynced_payments_exist(): void
    {
        $queryBuilderMock = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['count'])
            ->getMock();

        $queryBuilderMock->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $this
            ->paymentRepository
            ->expects($this->once())
            ->method('getNonSynchronisedPayments')
            ->willReturn(new Builder($queryBuilderMock));

        SlackAlert::shouldReceive('to')
            ->once()
            ->with('data-sync')
            ->andReturnSelf();

        $slackMessageBuilder = SlackMessageBuilder::instance()
            ->header(text: __('messages.payment.batch_processing.payment_sync_status_report_header'))
            ->messageContext(environment: app()->environment(), notify: null)
            ->section(text: __('messages.payment.batch_processing.payment_sync_payments_already_synced'))
            ->build();

        SlackAlert::shouldReceive('blocks')
            ->once()
            ->with($this->equalTo($slackMessageBuilder));

        $result = $this->handler()->handle();

        $this->assertEquals(new PaymentSyncReportDto(
            numberUnprocessed: 0,
            message: __('messages.payment.batch_processing.payment_sync_payments_already_synced')
        ), $result);
    }

    #[Test]
    public function it_sends_payment_sync_report_when_unsynced_payments_exist(): void
    {

        $queryBuilderMock = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['count'])
            ->getMock();

        $queryBuilderMock->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $eloquentBuilderMock = $this->getMockBuilder(Builder::class)
            ->setConstructorArgs([$queryBuilderMock])
            ->onlyMethods(['chunk'])
            ->getMock();

        $eloquentBuilderMock->expects($this->once())
            ->method('chunk')
            ->willReturnCallback(static function ($chunkSize, $callback) {
                $callback(new Collection(
                    [Payment::factory()->withoutRelationships()->make([
                        'id' => 'id1',
                        'amount' => 100,
                        'created_at' => now(),
                    ])]
                ));
            });

        $this
            ->paymentRepository
            ->expects($this->exactly(2))
            ->method('getNonSynchronisedPayments')
            ->willReturn($eloquentBuilderMock);

        SlackAlert::shouldReceive('to')
            ->twice()
            ->with('data-sync')
            ->andReturnSelf();

        SlackAlert::shouldReceive('blocks')
            ->twice()
            ->andReturnSelf();

        $result = $this->handler()->handle();

        $this->assertEquals(new PaymentSyncReportDto(
            numberUnprocessed: 1,
            message: __('messages.payment.batch_processing.payment_sync_report_processed')
        ), $result);
    }

    protected function handler(): PaymentSyncReportHandler
    {
        return new PaymentSyncReportHandler($this->paymentRepository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentRepository);
    }
}
