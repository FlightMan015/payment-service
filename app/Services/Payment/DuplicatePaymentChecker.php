<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Exceptions\PaymentSuspendedException;
use App\Exceptions\PaymentTerminatedException;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;

class DuplicatePaymentChecker
{
    protected Payment|null $originalPayment = null;

    public function getOriginalPayment(): Payment|null
    {
        return $this->originalPayment;
    }

    public function setOriginalPayment(Payment|null $originalPayment): void
    {
        $this->originalPayment = $originalPayment;
    }

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PaymentRepository $paymentRepository
    ) {
    }

    public function isDuplicatePayment(
        array $invoices,
        int|null $paymentAmount,
        Account $account,
        PaymentMethod $paymentMethod
    ): bool {

        $invoiceIds = collect($invoices)->pluck('id')->toArray();

        $latestPayment = $this->paymentRepository->getLatestSuccessfulPaymentForInvoices($account->id, $invoiceIds);

        if (!$latestPayment) {
            return false;
        }

        $latestTerminatedOrSuspendedPayment = $this->paymentRepository->getLatestSuspendedOrTerminatedPaymentForOriginalPayment(
            $account->id,
            $latestPayment->id
        );

        $this->checkExceptions($latestTerminatedOrSuspendedPayment, $account, $paymentAmount, $latestPayment);

        $isSameAmount = $latestPayment->amount === $paymentAmount;

        $isLastWeek = Carbon::parse($latestPayment->processed_at)->startOfDay() >= today()->subWeek();

        $isSamePaymentMethod = $latestPayment->payment_method_id === $paymentMethod->id;

        // Latest payment for invoices made sure this is always true
        $isForSameInvoices = $this->paymentRepository->checkIfPaymentMatchInvoices($latestPayment, $invoiceIds);

        // Latest payment for invoices made sure this is always true
        $isBatchPayment = $latestPayment->is_batch_payment;

        $isDuplicatePayment = $isSameAmount
            && $isLastWeek
            && $isSamePaymentMethod
            && $isForSameInvoices
            && $isBatchPayment;

        $this->log(
            $account,
            $isDuplicatePayment,
            $isSameAmount,
            $isLastWeek,
            $isSamePaymentMethod,
            $isForSameInvoices,
            $isBatchPayment
        );

        if ($isDuplicatePayment) {
            $this->setOriginalPayment($latestPayment);
        }

        return $isDuplicatePayment;
    }

    /**
     * @param Payment|null $latestTerminatedOrSuspendedPayment
     * @param Account $account
     * @param int|null $paymentAmount
     * @param Payment $latestPayment
     *
     * @return void
     */
    protected function checkExceptions(
        Payment|null $latestTerminatedOrSuspendedPayment,
        Account $account,
        int|null $paymentAmount,
        Payment $latestPayment
    ): void {
        if (!$latestTerminatedOrSuspendedPayment) {
            return;
        }

        $context = [
            'account_id' => $account->id,
            'account_balance' => $paymentAmount,
            'original_payment_id' => $latestPayment->original_payment_id,
        ];

        $suspendedContext = array_merge($context, ['suspended_payment_id' => $latestTerminatedOrSuspendedPayment->original_payment_id]);
        $terminatedContext = array_merge($context, ['terminated_payment_id' => $latestTerminatedOrSuspendedPayment->original_payment_id]);

        throw $latestTerminatedOrSuspendedPayment->payment_status_id === PaymentStatusEnum::SUSPENDED->value
            ? new PaymentSuspendedException(context: $suspendedContext)
            : new PaymentTerminatedException(context: $terminatedContext);

    }

    /**
     * @param Account $account
     * @param bool $isDuplicatePayment
     * @param bool $isSameAmount
     * @param bool $isLastWeek
     * @param bool $isSamePaymentMethod
     * @param bool $isForSameInvoices
     * @param bool $isBatchPayment
     *
     * @return void
     */
    protected function log(
        Account $account,
        bool $isDuplicatePayment,
        bool $isSameAmount,
        bool $isLastWeek,
        bool $isSamePaymentMethod,
        bool $isForSameInvoices,
        bool $isBatchPayment
    ): void {
        $this->logger->debug(
            message: sprintf('Checking payment suspension results for %s', $account->id),
            context: [
                'is_suspended' => $isDuplicatePayment,
                'conditions' => [
                    'same_amount' => $isSameAmount,
                    'within_7_days' => $isLastWeek,
                    'same_payment_method' => $isSamePaymentMethod,
                    'same_invoices' => $isForSameInvoices,
                    'is_batch_payment' => $isBatchPayment,
                ]
            ]
        );
    }
}
