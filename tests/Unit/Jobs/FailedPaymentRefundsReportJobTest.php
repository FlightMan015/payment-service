<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\Repositories\Interface\FailedRefundPaymentRepository;
use App\Helpers\MoneyHelper;
use App\Jobs\FailedPaymentRefundsReportJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Contact;
use App\Models\FailedRefundPayment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\Services\Communication\Email\EmailService;
use App\Services\FileGenerator\FileGenerator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use League\Csv\AbstractCsv;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class FailedPaymentRefundsReportJobTest extends UnitTestCase
{
    /** @var FileGenerator&MockObject $fileGenerator */
    private FileGenerator $fileGenerator;
    /** @var EmailService&MockObject $emailService */
    private EmailService $emailService;
    /** @var FailedRefundPaymentRepository&MockObject $repository */
    private FailedRefundPaymentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileGenerator = $this->createMock(FileGenerator::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->repository = $this->createMock(FailedRefundPaymentRepository::class);
    }

    #[Test]
    public function job_is_queued_in_the_correct_queue(): void
    {
        Queue::fake();

        FailedPaymentRefundsReportJob::dispatch();

        Queue::assertPushedOn(
            queue: config(key: 'queue.connections.sqs.queues.notifications'),
            job: FailedPaymentRefundsReportJob::class
        );
    }

    #[Test]
    public function it_adds_info_log_and_does_not_send_report_if_there_are_no_failures_to_report(): void
    {
        $this->repository->expects($this->once())
            ->method('getNotReported')
            ->willReturn(
                new LengthAwarePaginator(
                    items: collect(),
                    total: 0,
                    perPage: 10,
                    currentPage: 1,
                    options: []
                )
            );

        Log::expects('info')->with(__(key: 'messages.reports.failed_refunds.not_found'));

        $this->handleJob();
    }

    #[Test]
    public function it_sends_the_report_and_mark_failures_as_reported(): void
    {
        $account = Account::factory()->makeWithRelationships(
            relationships: [
                'billingContact' => Contact::factory()->make(),
            ]
        );
        $totalFailures = 5;
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);
        $originalPayment = Payment::factory()->makeWithRelationships(
            attributes: ['payment_status_id' => PaymentStatusEnum::CAPTURED->value],
            relationships: ['paymentMethod' => $paymentMethod, 'account' => $account]
        );
        $refundPayment = Payment::factory()->makeWithRelationships(
            attributes: ['payment_status_id' => PaymentStatusEnum::CREDITED->value],
            relationships: ['paymentMethod' => $paymentMethod, 'account' => $account]
        );
        /** @var Collection<int, FailedRefundPayment> $failures */
        $failures = FailedRefundPayment::factory()->count($totalFailures)->makeWithRelationships(relationships: [
            'account' => $account,
            'originalPayment' => $originalPayment,
            'refundPayment' => $refundPayment,
        ]);

        $this->repository->expects($this->once())
            ->method('getNotReported')
            ->willReturn(
                new LengthAwarePaginator(
                    items: $failures,
                    total: $totalFailures,
                    perPage: 10,
                    currentPage: 1,
                    options: []
                )
            );

        $file = $this->createMock(AbstractCsv::class);
        $this->fileGenerator->expects($this->once())->method('generateFile')
            ->with($this->buildArrayForCsv($failures))
            ->willReturn($file);
        $this->emailService->expects($this->once())->method('send');
        $this->emailService->method('getToEmail')->willReturn('testemail@goaptive.com');

        $this->repository->expects($this->exactly($totalFailures))->method('update');

        Log::expects('info')->once()->with(
            __(key: 'messages.reports.failed_refunds.email.sent', replace: ['email' => 'testemail@goaptive.com'])
        );

        $this->handleJob();
    }

    #[Test]
    public function it_sends_the_report_and_mark_failures_as_reported_with_pagaination(): void
    {
        $account = Account::factory()->makeWithRelationships(
            relationships: [
                'billingContact' => Contact::factory()->make(),
            ]
        );
        $totalFailures = 101;
        $originalPayment = Payment::factory()->withoutRelationships()->make(attributes: ['payment_status_id' => PaymentStatusEnum::CAPTURED->value]);
        $refundPayment = Payment::factory()->withoutRelationships()->make(attributes: ['payment_status_id' => PaymentStatusEnum::CREDITED->value]);
        /** @var Collection<int, FailedRefundPayment> $failuresFirstPage */
        $failuresFirstPage = FailedRefundPayment::factory()->count(100)->makeWithRelationships(relationships: [
            'account' => $account,
            'originalPayment' => $originalPayment,
            'refundPayment' => $refundPayment,
        ]);
        /** @var Collection<int, FailedRefundPayment> $failuresFirstPage */
        $failuresSecondPage = FailedRefundPayment::factory()->count(1)->makeWithRelationships(relationships: [
            'account' => $account,
            'originalPayment' => $originalPayment,
            'refundPayment' => $refundPayment,
        ]);

        $this->repository->expects($this->exactly(2))
            ->method('getNotReported')
            ->willReturnCallback(static fn ($page, $perPage) => match ($page) {
                1 => new LengthAwarePaginator(
                    items: $failuresFirstPage,
                    total: $totalFailures,
                    perPage: FailedPaymentRefundsReportJob::REFUNDS_BATCH_SIZE_PER_REQUEST,
                    currentPage: 1
                ),
                2 => new LengthAwarePaginator(
                    items: $failuresSecondPage,
                    total: $totalFailures,
                    perPage: FailedPaymentRefundsReportJob::REFUNDS_BATCH_SIZE_PER_REQUEST,
                    currentPage: 2
                ),
                default => throw new \RuntimeException('Unexpected page number')
            });

        $allFailures = $failuresFirstPage->collect()->merge(collect($failuresSecondPage));

        $file = $this->createMock(AbstractCsv::class);
        $this->fileGenerator->expects($this->once())->method('generateFile')
            ->with($this->buildArrayForCsv($allFailures))
            ->willReturn($file);
        $this->emailService->expects($this->once())->method('send');
        $this->emailService->method('getToEmail')->willReturn('testemail@goaptive.com');

        $this->repository->expects($this->exactly($totalFailures))->method('update');

        Log::expects('info')->once()->with(
            __(key: 'messages.reports.failed_refunds.email.sent', replace: ['email' => 'testemail@goaptive.com'])
        );

        $this->handleJob();
    }

    private function handleJob(): void
    {
        $job = new FailedPaymentRefundsReportJob();

        $job->handle(
            fileGenerator: $this->fileGenerator,
            emailService: $this->emailService,
            repository: $this->repository
        );
    }

    /**
     * @param Collection<int, FailedRefundPayment> $failures
     *
     * @return array<int, array<string>>
     */
    private function buildArrayForCsv(Collection $failures): array
    {
        $headers = [
            __(key: 'messages.reports.failed_refunds.header.first_name'),
            __(key: 'messages.reports.failed_refunds.header.last_name'),
            __(key: 'messages.reports.failed_refunds.header.customer_id'),
            __(key: 'messages.reports.failed_refunds.header.account_id'),
            __(key: 'messages.reports.failed_refunds.header.payment_id'),
            __(key: 'messages.reports.failed_refunds.header.payment_date'),
            __(key: 'messages.reports.failed_refunds.header.original_amount'),
            __(key: 'messages.reports.failed_refunds.header.refund_amount'),
            __(key: 'messages.reports.failed_refunds.header.refund_date')
        ];

        $data = $failures->map(callback: static function (FailedRefundPayment $failedRefundPayment) {
            return [
                $failedRefundPayment->account->billingContact->first_name ?: $failedRefundPayment->account->contact->first_name,
                $failedRefundPayment->account->billingContact->last_name ?: $failedRefundPayment->account->contact->last_name,
                $failedRefundPayment->account->external_ref_id,
                $failedRefundPayment->account->id,
                $failedRefundPayment->original_payment_id,
                Carbon::parse($failedRefundPayment->originalPayment->processed_at)->format(format: 'Y-m-d'),
                MoneyHelper::convertToDecimal($failedRefundPayment->originalPayment->amount),
                MoneyHelper::convertToDecimal($failedRefundPayment->amount),
                Carbon::parse($failedRefundPayment->failed_at)->format(format: 'Y-m-d')
            ];
        })->toArray();

        return array_merge([$headers], $data);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->fileGenerator, $this->emailService, $this->repository);
    }
}
