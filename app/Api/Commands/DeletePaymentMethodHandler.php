<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Exceptions\InvalidPaymentMethodStateException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\PaymentMethod;

class DeletePaymentMethodHandler
{
    private PaymentMethod $paymentMethod;

    /**
     * @param PaymentMethodRepository $repository
     */
    public function __construct(private readonly PaymentMethodRepository $repository)
    {
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @throws InvalidPaymentMethodStateException
     *
     * @return bool
     */
    public function handle(PaymentMethod $paymentMethod): bool
    {
        $this->paymentMethod = $paymentMethod;

        $this->validatePaymentMethodState();

        return $this->repository->softDelete($this->paymentMethod);
    }

    /**
     * @throws InvalidPaymentMethodStateException
     */
    private function validatePaymentMethodState(): void
    {
        if ($this->paymentMethod->is_primary) {
            throw new InvalidPaymentMethodStateException(
                message: __('messages.payment_method.primary.cannot_delete'),
            );
        }
    }
}
