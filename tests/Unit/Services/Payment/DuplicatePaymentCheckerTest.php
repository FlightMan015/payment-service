<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Exceptions\PaymentSuspendedException;
use App\Exceptions\PaymentTerminatedException;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\Services\Payment\DuplicatePaymentChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Unit\UnitTestCase;

class DuplicatePaymentCheckerTest extends UnitTestCase
{
    protected int $paymentAmount;

    /** @var PaymentRepository&MockObject $paymentRepository */
    private readonly PaymentRepository $paymentRepository;

    private Account $account;

    private PaymentMethod $paymentMethod;

    private DuplicatePaymentChecker $duplicatePaymentChecker;

    protected function setUp(): void
    {
        parent::setUp();
        $loggerInterface = $this->createMock(LoggerInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->account = Account::factory()->withoutRelationships()->make();
        $this->paymentMethod = PaymentMethod::factory()->withoutRelationships()->make();
        $this->duplicatePaymentChecker = new DuplicatePaymentChecker(
            logger: $loggerInterface,
            paymentRepository: $this->paymentRepository
        );
        $this->paymentAmount = 10;
    }

    #[Test]
    public function it_should_get_latest_payment(): void
    {
        $payment = Payment::factory()->withoutRelationships()->make();

        $this->duplicatePaymentChecker->setOriginalPayment($payment);

        $this->assertEquals($payment->id, $this->duplicatePaymentChecker->getOriginalPayment()?->id);
    }

    #[Test]
    public function it_should_return_false_when_invoice_empty(): void
    {
        $this->paymentRepository->method('getLatestSuccessfulPaymentForInvoices')->willReturn(null);

        $isDuplicatePayment = $this->duplicatePaymentChecker->isDuplicatePayment(
            invoices: [],
            paymentAmount: null,
            account: $this->account,
            paymentMethod: $this->paymentMethod,
        );

        $this->assertFalse($isDuplicatePayment);
    }

    #[Test]
    public function it_should_return_true_when_duplicated_payment(): void
    {
        $this->paymentRepository->method('getLatestSuccessfulPaymentForInvoices')->willReturn($this->getLatestPayment());
        $this->paymentRepository->method('getLatestSuspendedOrTerminatedPaymentForOriginalPayment')->willReturn(null);
        $this->paymentRepository->method('checkIfPaymentMatchInvoices')->willReturn(true);

        $isDuplicatePayment = $this->duplicatePaymentChecker->isDuplicatePayment(
            invoices: [],
            paymentAmount: $this->paymentAmount,
            account: $this->account,
            paymentMethod: $this->paymentMethod,
        );

        $this->assertTrue($isDuplicatePayment);
    }

    #[Test]
    public function it_should_throw_payment_terminated_exception_when_terminated_payment(): void
    {
        $latestTerminatedOrSuspendedPayment = Payment::factory()->withoutRelationships()->make(['payment_status_id' => PaymentStatusEnum::TERMINATED->value]);
        $this->paymentRepository->method('getLatestSuccessfulPaymentForInvoices')->willReturn($this->getLatestPayment());
        $this->paymentRepository->method('getLatestSuspendedOrTerminatedPaymentForOriginalPayment')->willReturn($latestTerminatedOrSuspendedPayment);

        $this->expectException(exception: PaymentTerminatedException::class);
        $this->getDuplicatePayment();

    }

    #[Test]
    public function it_should_throw_payment_suspended_exception_when_suspended_payment(): void
    {
        $latestTerminatedOrSuspendedPayment = Payment::factory()->withoutRelationships()->make(['payment_status_id' => PaymentStatusEnum::SUSPENDED->value]);
        $this->paymentRepository->method('getLatestSuccessfulPaymentForInvoices')->willReturn($this->getLatestPayment());
        $this->paymentRepository->method('getLatestSuspendedOrTerminatedPaymentForOriginalPayment')->willReturn($latestTerminatedOrSuspendedPayment);

        $this->expectException(exception: PaymentSuspendedException::class);
        $this->getDuplicatePayment();

    }

    /**
     * @return void
     */
    protected function getDuplicatePayment(): void
    {
        $this->duplicatePaymentChecker->isDuplicatePayment(
            invoices: [],
            paymentAmount: $this->paymentAmount,
            account: $this->account,
            paymentMethod: $this->paymentMethod,
        );
    }

    /**
     * @return Payment
     */
    protected function getLatestPayment(): Payment
    {
        return Payment::factory()->withoutRelationships()->make([
            'amount' => $this->paymentAmount,
            'processed_at' => today(),
            'payment_method_id' => $this->paymentMethod->id,
            'is_batch_payment' => true,
        ]);
    }

}
