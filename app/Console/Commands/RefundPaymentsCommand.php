<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Console\Summary\PaymentRefunds\RefundCommandSummaryItem;
use App\Models\Payment;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;
use App\Services\Payment\Refund\RefundElectronicPaymentService;
use App\Traits\PrintsAndLogsOutput;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\TableSeparator;

class RefundPaymentsCommand extends Command
{
    use PrintsAndLogsOutput;

    private const int DAYS_THE_REFUND_IS_ALLOWED = 45;
    private const int START_FAKE_EXTERNAL_REF_ID_VALUE = 1000000000;

    private RefundElectronicPaymentService $refundService;
    private PaymentRepository $paymentRepository;
    private PaymentTransactionRepository $transactionRepository;
    /** @var RefundCommandSummaryItem[] $summary */
    private array $summary;

    private bool|null $populateFakeExternalRefId;
    private int|null $daysRefundIsAllowed;
    private array $paymentIds;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refund:full
                            {ids* : The list of IDs of payments that should be refunded}
                            {--days-allowed= : The days the electronic refund is allowed, default value is 45, max value is 45}
                            {--populate-fake-external-ref-id : To set fake external ref id to the refund payment so it will not be synced during sync process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refund through gateway the full amount of each payment ID given';

    /**
     * Execute the console command.
     *
     * @param RefundElectronicPaymentService $service
     * @param PaymentRepository $paymentRepository
     * @param PaymentTransactionRepository $transactionRepository
     */
    public function handle(
        RefundElectronicPaymentService $service,
        PaymentRepository $paymentRepository,
        PaymentTransactionRepository $transactionRepository
    ): void {
        Log::info(message: 'refund:full command handling started');

        $this->refundService = $service;
        $this->paymentRepository = $paymentRepository;
        $this->transactionRepository = $transactionRepository;
        $this->summary = ['cc' => new RefundCommandSummaryItem(), 'ach' => new RefundCommandSummaryItem()];

        $this->parseParameters();
        $this->processRefunds();

        $this->printSummaryReport();

        Log::withoutContext()->info(message: 'refund:full command handling finished');
    }

    private function parseParameters(): void
    {
        $this->paymentIds = $this->argument(key: 'ids');
        $this->daysRefundIsAllowed = $this->option(key: 'days-allowed')
            ? (int)$this->option('days-allowed')
            : self::DAYS_THE_REFUND_IS_ALLOWED;
        $this->populateFakeExternalRefId = $this->option(key: 'populate-fake-external-ref-id') ?: false;

        $this->validateParameters();
    }

    private function validateParameters(): void
    {
        if ($this->daysRefundIsAllowed > RefundElectronicPaymentService::ELECTRONIC_REFUND_DAYS_TECHNICAL_LIMIT) {
            throw new \InvalidArgumentException(
                message: __(
                    'messages.operation.refund.exceed_technical_limit',
                    ['days' => RefundElectronicPaymentService::ELECTRONIC_REFUND_DAYS_TECHNICAL_LIMIT]
                )
            );
        }
    }

    private function processRefunds(): void
    {
        foreach ($this->paymentIds as $paymentId) {
            Log::flushSharedContext();
            Log::shareContext(context: ['original_payment_id' => $paymentId]);

            try {
                $payment = $this->paymentRepository->find(paymentId: $paymentId);
                $this->refundPayment($payment);
            } catch (\Throwable $exception) {
                $this->printAndLogError(
                    sprintf('Error refunding Payment ID: %s, reason: %s', $paymentId, $exception->getMessage())
                );

                if (isset($payment)) {
                    $paymentType = $payment->payment_type->isCreditCardType() ? 'cc' : 'ach';
                    $this->summary[$paymentType]->incrementFailed();
                    $this->summary[$paymentType]->increaseFailedAmount($payment->amount);
                }

                continue;
            }
        }
    }

    /**
     * @param Payment $payment
     *
     * @throws \Throwable
     */
    private function refundPayment(Payment $payment): void
    {
        Log::shareContext(context: [
            'account_id' => $payment->account_id,
            'account_external_ref_id' => $payment->account->external_ref_id,
            'original_payment_type_id' => $payment->payment_type,
            'original_payment_transaction_id' => $payment->transactionForOperation(
                operation: [OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE]
            )?->id,
            'original_payment_amount' => $payment->amount,
            'original_payment_processed_at' => $payment->processed_at,
        ]);

        $this->validateIfPaymentCouldBeRefunded($payment);

        // determine fake external ref id if needed
        if ($this->populateFakeExternalRefId) {
            $lastExternalRefId = Payment::withTrashed()
                ->where('external_ref_id', '>=', self::START_FAKE_EXTERNAL_REF_ID_VALUE)
                ->max('external_ref_id');
            $fakeExternalRefId = $lastExternalRefId ? $lastExternalRefId + 1 : self::START_FAKE_EXTERNAL_REF_ID_VALUE;
        } else {
            $fakeExternalRefId = null;
        }

        $this->printAndLogInfo(sprintf('Attempting Refund of Payment ID: %s', $payment->id));

        $returnResult = $this->refundService->refund(
            paymentRefundDto: new MakePaymentRefundDto(
                originalPayment: $payment,
                refundAmount: $payment->amount,
                daysRefundAllowed: $this->daysRefundIsAllowed,
                externalRefId: $fakeExternalRefId
            )
        );

        $this->handleRefundResult(payment: $payment, result: $returnResult);

        Log::flushSharedContext();
    }

    private function validateIfPaymentCouldBeRefunded(Payment $payment): void
    {
        $this->validatePaymentType($payment);
    }

    private function validatePaymentType(Payment $payment): void
    {
        if (!in_array($payment->payment_type, PaymentTypeEnum::electronicTypes(), true)) {
            throw new \RuntimeException(message: 'Only electronic payments could be refunded');
        }
    }

    private function handleRefundResult(Payment $payment, RefundPaymentResultDto $result): void
    {
        $paymentSummaryType = $payment->payment_type->isCreditCardType() ? 'cc' : 'ach';

        if ($result->isSuccess) {
            $transaction = $this->transactionRepository->findById(transactionId: $result->transactionId);

            Log::shareContext(context: [
                'refund_payment_id' => $transaction->payment_id,
                'refund_payment_amount' => $transaction->payment->amount,
                'refund_payment_transaction_id' => $transaction->id,
                'refund_processed_at' => $transaction->payment->processed_at,
            ]);

            $this->printAndLogInfo(sprintf('Refunded Payment ID: %s successfully', $payment->id));

            $this->summary[$paymentSummaryType]->incrementSuccessful();
            $this->summary[$paymentSummaryType]->increaseSuccessfulAmount($payment->amount);

            return;
        }

        $this->printAndLogError(
            sprintf('Error refunding Payment ID: %s, error: %s', $payment->id, $result->errorMessage)
        );

        $this->summary[$paymentSummaryType]->incrementFailed();
        $this->summary[$paymentSummaryType]->increaseFailedAmount($payment->amount);
    }

    private function printSummaryReport(): void
    {
        $this->table(
            headers: ['Payment Type', 'Successfully #', 'Successfully $', 'Failed #', 'Failed $'],
            rows: [
                ['type' => 'Credit Card'] + $this->summary['cc']->toArray(),
                ['type' => 'ACH'] + $this->summary['ach']->toArray(),
                new TableSeparator(),
                ['type' => 'Total'] + array_map(
                    static fn (float|int $a, float|int $b) => $a + $b,
                    $this->summary['cc']->toArray(),
                    $this->summary['ach']->toArray()
                ),
            ],
        );

        // TODO: add table with detail information about fail reasons if needed
    }
}
