<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\Payment;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;
use App\Services\Payment\Refund\RefundElectronicPaymentService;
use Aptive\Attribution\Enums\DomainEnum;
use Aptive\Attribution\Enums\EntityEnum;
use Aptive\Attribution\Enums\PrefixEnum;
use Aptive\Attribution\Enums\TenantEnum;
use Aptive\Attribution\Urn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEligibleRefundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 1;
    private Urn $paymentServiceApiUrn;
    private PaymentRepository $paymentRepository;

    /**
     * Create a new job instance
     *
     * @param Payment $refund
     */
    public function __construct(private readonly Payment $refund)
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
        $this->paymentServiceApiUrn = new Urn(
            prefix: PrefixEnum::URN,
            tenant: TenantEnum::Aptive,
            domain: DomainEnum::Organization,
            entity: EntityEnum::ApiAccount,
            identity: config(key: 'attribution.payment_service_api_account_id')
        );
    }

    /**
     * Execute the job.
     *
     * @param RefundElectronicPaymentService $refundService
     * @param PaymentRepository $paymentRepository
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function handle(RefundElectronicPaymentService $refundService, PaymentRepository $paymentRepository): void
    {
        Log::shareContext(context: [
            'payment_id' => $this->refund->id,
            'external_ref_id' => $this->refund->external_ref_id,
            'account_id' => $this->refund->account_id,
            'amount' => $this->refund->amount,
        ]);

        $this->paymentRepository = $paymentRepository;

        try {
            $this->validatePaymentState();
        } catch (\RuntimeException) {
            // todo: send notification CLEO-1097
            $this->markPaymentAsProcessed();
            return;
        } catch (\LogicException) {
            // skip it (todo: change exception to be more concrete)
            return;
        }

        Log::info(message: __('messages.payment.eligible_refunds_processing.start_refund'));

        $refundDto = new MakePaymentRefundDto(
            originalPayment: $this->refund->originalPayment,
            refundAmount: $this->refund->amount,
            daysRefundAllowed: RefundElectronicPaymentService::ELECTRONIC_REFUND_DAYS_TECHNICAL_LIMIT,
            existingRefundPayment: $this->refund
        );

        $result = $refundService->refund(paymentRefundDto: $refundDto);

        if (!$result->isSuccess) {
            // todo: send notification CLEO-1097
            Log::warning(message: __('messages.payment.eligible_refunds_processing.refund_failed'), context: ['error' => $result->errorMessage]);
            $this->markPaymentAsProcessed();

            return;
        }

        $this->markPaymentAsProcessed();

        Log::info(message: __('messages.payment.eligible_refunds_processing.refunded_successfully'), context: ['transaction_id' => $result->transactionId]);
    }

    private function validatePaymentState(): void
    {
        $this->validateIfPaymentMethodExists();
        $this->validateIfOriginalPaymentWasAlreadyProcessed();
    }

    private function validateIfPaymentMethodExists(): void
    {
        if (is_null($this->refund->paymentMethod)) {
            Log::warning(message: __('messages.payment.eligible_refunds_processing.payment_method_not_found_cannot_process'), context: ['payment_id' => $this->refund->id]);
            throw new \RuntimeException(message: __('messages.payment.eligible_refunds_processing.payment_method_not_found'));
        }
    }

    private function validateIfOriginalPaymentWasAlreadyProcessed(): void
    {
        if (!$this->refund->originalPayment->wasAlreadyProcessedInGateway()) {
            Log::warning(message: __('messages.payment.eligible_refunds_processing.original_payment_was_not_processed'), context: ['payment_id' => $this->refund->id]);
            throw new \LogicException(message: __('messages.payment.eligible_refunds_processing.original_payment_was_not_processed'));
        }
    }

    private function markPaymentAsProcessed(): void
    {
        $this->paymentRepository->update(
            payment: $this->refund,
            attributes: [
                'pestroutes_refund_processed_at' => now(),
                'updated_by' => $this->paymentServiceApiUrn->toString()
            ]
        );
    }
}
