<?php

declare(strict_types=1);

namespace App\Jobs\ScheduledPayment\Triggers;

use App\Entities\Enums\AccountStatusEnum;
use App\Entities\Enums\SubscriptionInitialStatusEnum;
use App\Entities\Subscription;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Support\Facades\Log;

class InitialServiceCompletedScheduledPaymentTriggerJob extends AbstractScheduledPaymentTriggerJob
{
    private Subscription $subscription;

    protected function validatePaymentTrigger(): void
    {
        if ($this->payment->payment_trigger !== ScheduledPaymentTriggerEnum::InitialServiceCompleted) {
            throw new \InvalidArgumentException(__('messages.scheduled_payment.invalid_trigger'));
        }
    }

    protected function areRelatedEntitiesInValidState(): bool
    {
        return $this->checkIfAccountIsActive()
            && $this->checkIfSubscriptionIsActive()
            && $this->checkIfPaymentMethodIsNotDeleted();
    }

    protected function checkIfPaymentShouldBeProcessed(): bool
    {
        return $this->checkIfInitialServiceWasCompleted();
    }

    private function checkIfInitialServiceWasCompleted(): bool
    {
        return $this->subscription->initialStatus === SubscriptionInitialStatusEnum::COMPLETED;
    }

    private function retrieveSubscription(): void
    {
        $subscriptionService = app(abstract: SubscriptionServiceInterface::class);
        $this->subscription = $subscriptionService->getSubscription(id: $this->metadata->subscription_id);
    }

    /**
     * TODO: refactor to use CRM API at some point [CLEO-1301]
     *
     * @return bool
     */
    private function checkIfAccountIsActive(): bool
    {
        $isAccountActive = !$this->payment->account->trashed()
            && $this->payment->account->is_active
            && $this->payment->account->status === AccountStatusEnum::ACTIVE->value;

        if (!$isAccountActive) {
            Log::warning(message: __('messages.scheduled_payment.inactive_account'), context: ['account_id' => $this->payment->account->id]);
        }

        return $isAccountActive;
    }

    private function checkIfSubscriptionIsActive(): bool
    {
        $this->retrieveSubscription();
        $isSubscriptionActive = $this->subscription->isActive;

        if (!$isSubscriptionActive) {
            Log::warning(
                message: __('messages.scheduled_payment.inactive_subscription'),
                context: ['subscription_id' => $this->subscription->id]
            );
        }

        return $isSubscriptionActive;
    }

    private function checkIfPaymentMethodIsNotDeleted(): bool
    {
        if ($this->payment->paymentMethod->trashed()) {
            Log::warning(
                message: __('messages.scheduled_payment.payment_method_soft_deleted'),
                context: ['payment_method_id' => $this->payment->payment_method_id]
            );
            return false;
        }

        return true;
    }
}
