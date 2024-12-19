<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\PaymentSettledEvent;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PaymentSettledEventTest extends UnitTestCase
{
    #[Test]
    public function it_creates_a_timestamp_when_creating_the_event(): void
    {
        Carbon::setTestNow(now());

        $paymentType = PaymentType::factory()->make();
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::SETTLED->value,
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            ],
            relationships: [
                'account' => Account::factory()->withoutRelationships()->make(),
                'paymentMethod' => PaymentMethod::factory()->withoutRelationships()->make(),
                'transactions' => Transaction::factory()->withoutRelationships()->make([
                    'gateway_response_code' => (string) WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_SETTLED->value,
                    'raw_response_log' => json_encode([0, 'Success']),
                ]),
                'type' => $paymentType
            ]
        );

        $event = new PaymentSettledEvent(payment: $payment);

        $this->assertNotNull($event->timestamp);
        $this->assertEquals($event->timestamp, now()->getTimestampMs());

        Carbon::setTestNow(null);
    }
}
