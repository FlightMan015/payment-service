<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\CRM\SubscriptionRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;

class UpdateSubscriptionAutopayStatusHandler
{
    private UpdateSubscriptionAutopayStatusCommand|null $command = null;
    private PaymentMethod|null $paymentMethod = null;

    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param SubscriptionRepository $subscriptionRepository
     */
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly SubscriptionRepository $subscriptionRepository
    ) {
    }

    /**
     * @param UpdateSubscriptionAutopayStatusCommand $command
     *
     *@throws PaymentValidationException
     * @throws ResourceNotFoundException
     *
     * @return void
     */
    public function handle(UpdateSubscriptionAutopayStatusCommand $command): void
    {
        $this->command = $command;
        Log::withContext(context: ['request' => $this->command->toArray()]);

        $this->retrievePaymentMethod();
        $this->setSubscriptionAutopayPaymentMethodInDatabase();
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function retrievePaymentMethod(): void
    {
        if (is_null($this->command->autopayPaymentMethodId)) {
            $this->paymentMethod = null;

            return;
        }

        $this->paymentMethod = $this->paymentMethodRepository->find(paymentMethodId: $this->command->autopayPaymentMethodId);
    }

    /**
     * @throws PaymentValidationException
     */
    private function setSubscriptionAutopayPaymentMethodInDatabase(): void
    {
        try {
            $this->subscriptionRepository->setAutoPayPaymentMethod(
                subscription: $this->subscriptionRepository->find(id: $this->command->subscriptionId),
                autopayPaymentMethod: $this->paymentMethod
            );
        } catch (PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException $exception) {
            throw new PaymentValidationException(
                message: __('messages.invalid_input'),
                errors: [$exception->getMessage()]
            );
        }
    }
}
