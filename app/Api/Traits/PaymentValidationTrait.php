<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;

trait PaymentValidationTrait
{
    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    /**
     * @throws PaymentValidationException
     * @throws \Throwable
     */
    private function retrieveAndValidatePaymentMethodExistsAndBelongsToAccount(): void
    {
        if (is_null($this->command->paymentMethodId)) {
            $this->paymentMethod = $this->paymentMethodRepository->findPrimaryForAccount(accountId: $this->command->accountId);

            throw_if(
                condition: is_null($this->paymentMethod),
                exception: new PaymentValidationException(
                    message: __('messages.invalid_input'),
                    errors: [__('messages.operation.primary_payment_method_not_found')]
                )
            );

            return;
        }

        try {
            $this->paymentMethod = $this->paymentMethodRepository->find($this->command->paymentMethodId);
        } catch (ResourceNotFoundException) {
            throw new PaymentValidationException(
                message: __('messages.invalid_input'),
                errors: [__('messages.operation.given_payment_method_not_found')]
            );
        }

        throw_if(
            condition: $this->paymentMethod->account->id !== $this->command->accountId,
            exception: new PaymentValidationException(
                message: __('messages.invalid_input'),
                errors: [__('messages.operation.given_payment_method_not_belong_to_account')]
            )
        );
    }
}
