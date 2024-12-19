<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ScheduledPaymentTriggerInvalidMetadataException;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use Illuminate\Support\Facades\Log;

readonly class InitialServiceCompletedScheduledPaymentTriggerValidator implements ScheduledPaymentTriggerMetadataValidatorInterface
{
    /**
     * @param SubscriptionServiceInterface $subscriptionService
     */
    public function __construct(private SubscriptionServiceInterface $subscriptionService)
    {
    }

    /**
     * @param array $metadata
     *
     * @throws ScheduledPaymentTriggerInvalidMetadataException
     *
     * @return void
     */
    public function validate(array $metadata = []): void
    {
        if (!isset($metadata['subscription_id'])) {
            throw new ScheduledPaymentTriggerInvalidMetadataException(__('messages.scheduled_payment.metadata_missing_information', ['property' => 'subscription_id']));
        }

        try {
            $this->subscriptionService->getSubscription($metadata['subscription_id']);
        } catch (\Throwable $exception) {
            Log::error('Error loading subscription', ['exception' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            throw new ScheduledPaymentTriggerInvalidMetadataException(__('messages.scheduled_payment.subscription_not_found'));
        }
    }
}
