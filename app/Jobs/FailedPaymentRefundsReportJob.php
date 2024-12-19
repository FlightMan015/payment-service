<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Repositories\Interface\FailedRefundPaymentRepository;
use App\Helpers\MoneyHelper;
use App\Models\FailedRefundPayment;
use App\Services\Communication\Email\EmailService;
use App\Services\Communication\Email\EmailServiceAttachment;
use App\Services\FileGenerator\FileGenerator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use League\Csv\AbstractCsv;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;

class FailedPaymentRefundsReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const string REPORT_FILE_NAME = 'failed_refunds_report.csv';
    public const int REFUNDS_BATCH_SIZE_PER_REQUEST = 100;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 1;

    private FileGenerator $fileGenerator;
    private EmailService $emailService;
    private FailedRefundPaymentRepository $repository;
    /** @var Collection<int, FailedRefundPayment> */
    private Collection $refunds;

    public function __construct()
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.notifications'));
    }

    /**
     * @param FileGenerator $fileGenerator
     * @param EmailService $emailService
     * @param FailedRefundPaymentRepository $repository
     *
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function handle(
        FileGenerator $fileGenerator,
        EmailService $emailService,
        FailedRefundPaymentRepository $repository
    ): void {
        $this->fileGenerator = $fileGenerator;
        $this->emailService = $emailService;
        $this->repository = $repository;

        try {
            $this->retrieveRefunds();
        } catch (\LogicException $e) {
            Log::info(message: $e->getMessage());

            return;
        }

        $file = $this->fileGenerator->generateFile($this->prepareData());

        $this->sendEmail($file);
        $this->markRefundsAsReported();
    }

    private function retrieveRefunds(): void
    {
        $refunds = [];
        $page = 1;

        do {
            $paginatedRefunds = $this->repository->getNotReported(
                page: $page,
                quantity: self::REFUNDS_BATCH_SIZE_PER_REQUEST
            );

            array_push($refunds, ...$paginatedRefunds->items());

            $page++;
        } while (count($refunds) < $paginatedRefunds->total());

        $this->refunds = collect($refunds);

        if ($this->refunds->isEmpty()) {
            throw new \LogicException(__(key: 'messages.reports.failed_refunds.not_found'));
        }
    }

    private function prepareData(): array
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

        $csvData = $this->refunds->map(callback: static function (FailedRefundPayment $failedRefundPayment) {
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

        return array_merge([$headers], $csvData);
    }

    /**
     * @param AbstractCsv $file
     *
     * @throws Exception
     */
    private function sendEmail(AbstractCsv $file): void
    {
        $this->emailService->setToEmail(
            toEmail: config(key: 'marketing-messaging-api.receivers.failed_payment_refunds')
        );

        $subject = __(key: 'messages.reports.failed_refunds.email.subject');
        $body = trans_choice(
            key: 'messages.reports.failed_refunds.email.body',
            number: $this->refunds->count(),
            replace: ['count' => $this->refunds->count()]
        );

        $refundsReportAttachment = new EmailServiceAttachment(
            content: base64_encode($file->toString()),
            fileName: self::REPORT_FILE_NAME,
            type: $this->fileGenerator->contentType(),
            disposition: 'attachment'
        );

        $this->emailService->send(
            templateName: 'basicTemplate',
            templateData: ['emailSubject' => $subject, 'emailBody' => $body],
            attachments: [$refundsReportAttachment]
        );

        Log::info(message: __(
            key: 'messages.reports.failed_refunds.email.sent',
            replace: ['email' => $this->emailService->getToEmail()]
        ));
    }

    private function markRefundsAsReported(): void
    {
        $this->refunds->each(callback: function (FailedRefundPayment $failedRefundPayment) {
            $this->repository->update(refund: $failedRefundPayment, attributes: ['report_sent_at' => now()]);
        });
    }
}
