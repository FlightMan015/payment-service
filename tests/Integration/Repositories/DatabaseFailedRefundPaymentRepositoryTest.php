<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Api\Repositories\DatabaseFailedRefundPaymentRepository;
use App\Models\FailedRefundPayment;
use App\Models\Payment;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseFailedRefundPaymentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_the_list_of_not_reported_failed_refunds(): void
    {
        // in case if there are any failed refunds in the database
        FailedRefundPayment::query()->delete();

        $totalFailedRefunds = 5;
        $alreadyReportedRefunds = 2;

        $payments = Payment::factory()->count($totalFailedRefunds)->create();

        $refundPayments = $payments->map(static function (Payment $payment) {
            $refund = $payment->replicate();
            $refund->payment_status_id = PaymentStatusEnum::DECLINED->value;
            $refund->original_payment_id = $payment->id;
            $refund->save();
            return $refund;
        });

        $failedRefunds = $refundPayments->map(static function (Payment $payment) {
            return FailedRefundPayment::factory()->create([
                'original_payment_id' => $payment->originalPayment->id,
                'report_sent_at' => null,
                'refund_payment_id' => $payment->id,
            ]);
        });

        $failedRefunds->shuffle()->take($alreadyReportedRefunds)->map(static function (FailedRefundPayment $failedRefund) {
            $failedRefund->update(['report_sent_at' => now()]);
        });

        $repository = new DatabaseFailedRefundPaymentRepository();
        $response = $repository->getNotReported(1, 10);

        $this->assertCount($totalFailedRefunds - $alreadyReportedRefunds, $response);
    }

    #[Test]
    public function it_updates_corresponding_fields_and_return_failed_refund_payment_record(): void
    {
        $payment = Payment::factory()->create();

        $refundPayment = $payment->replicate();
        $refundPayment->payment_status_id = PaymentStatusEnum::DECLINED->value;
        $refundPayment->original_payment_id = $payment->id;
        $refundPayment->save();

        $failedRefundPayment = FailedRefundPayment::factory()->create([
            'original_payment_id' => $refundPayment->originalPayment->id,
            'report_sent_at' => null,
            'refund_payment_id' => $refundPayment->id,
        ]);

        Carbon::setTestNow(now());

        $repository = new DatabaseFailedRefundPaymentRepository();
        $actual = $repository->update(refund: $failedRefundPayment, attributes: ['report_sent_at' => now()]);

        $this->assertInstanceOf(FailedRefundPayment::class, $actual);
        $this->assertEquals(now(), $actual->report_sent_at);
        $this->assertEquals($failedRefundPayment->id, $actual->id);
    }
}
