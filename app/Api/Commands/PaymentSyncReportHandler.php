<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\PaymentSyncReportDto;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Helpers\SlackMessageBuilder;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class PaymentSyncReportHandler
{
    protected const int PAYMENTS_PER_MESSAGE = 35;

    /**
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        protected readonly PaymentRepository $paymentRepository
    ) {
    }

    /**
     * @return PaymentSyncReportDto
     */
    public function handle(): PaymentSyncReportDto
    {
        $numUnsyncedPayments = $this->paymentRepository->getNonSynchronisedPayments()->count();

        if ($numUnsyncedPayments === 0) {
            $this->notifySlackWithMessage(
                message: __('messages.payment.batch_processing.payment_sync_payments_already_synced'),
                notify: null
            );

            return new PaymentSyncReportDto(
                numberUnprocessed: $numUnsyncedPayments,
                message: __('messages.payment.batch_processing.payment_sync_payments_already_synced')
            );
        }

        $this->notifySlackWithMessage(
            message: __('messages.payment.batch_processing.payments_not_synced_count', ['count' => $numUnsyncedPayments]),
            notify: '!channel'
        );

        $this->paymentRepository
            ->getNonSynchronisedPayments()
            // note: chunk value is lower to prevent slack from rejecting the block as too large
            ->chunk(self::PAYMENTS_PER_MESSAGE, function (Collection $payments) {
                $output = new BufferedOutput();
                $table = $this->generateTableWithHeader($output);
                $this->addPaymentsToTable($payments, $table);
                $table->render();

                SlackAlert::to('data-sync')->blocks(
                    blocks: SlackMessageBuilder::instance()
                        ->codeBlock($output->fetch())
                        ->build()
                );
            });

        return new PaymentSyncReportDto(
            numberUnprocessed: $numUnsyncedPayments,
            message: __('messages.payment.batch_processing.payment_sync_report_processed')
        );
    }

    private function notifySlackWithMessage(string $message, string|null $notify = null): void
    {
        SlackAlert::to('data-sync')->blocks(
            blocks: SlackMessageBuilder::instance()
                ->header(text: __('messages.payment.batch_processing.payment_sync_status_report_header'))
                ->messageContext(environment: app()->environment(), notify: $notify)
                ->section(text: $message)
                ->build()
        );
    }

    private function generateTableWithHeader(BufferedOutput $output): Table
    {
        return (new Table($output))
            ->setHeaders(['Id', 'Amount', 'Timestamp'])
            ->setColumnWidth(0, 10)
            ->setColumnWidth(1, 10)
            ->setColumnWidth(2, 10);
    }

    /**
     * @param Collection<int, Payment> $payments
     * @param Table $table
     *
     * @return void
     */
    private function addPaymentsToTable(Collection $payments, Table $table): void
    {
        $payments->each(function (Payment $payment) use ($table) {
            $this->addPaymentToTable($payment, $table);
        });
    }

    private function addPaymentToTable(Payment $payment, Table $table): void
    {
        $table->addRow([
            $payment->id,
            number_format($payment->getDecimalAmount(), 2),
            $payment->created_at->format('Y-m-d H:i:s')
        ]);
    }
}
