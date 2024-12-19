<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;

class UpdateAccountAutopayStatusHandler
{
    private UpdateAccountAutopayStatusCommand|null $command = null;
    private PaymentMethod|null $paymentMethod = null;

    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param AccountRepository $accountRepository
     */
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly AccountRepository $accountRepository
    ) {
    }

    /**
     * @param UpdateAccountAutopayStatusCommand $command
     *
     * @throws ResourceNotFoundException
     * @throws PaymentValidationException
     *
     * @return void
     */
    public function handle(UpdateAccountAutopayStatusCommand $command): void
    {
        $this->command = $command;
        Log::withContext(context: ['request' => $this->command->toArray()]);

        $this->retrievePaymentMethod();
        $this->setAccountAutopayPaymentMethodInDatabase();
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
    private function setAccountAutopayPaymentMethodInDatabase(): void
    {
        try {
            $this->accountRepository->setAutoPayPaymentMethod(
                account: $this->accountRepository->find(id: $this->command->accountId),
                autopayPaymentMethod: $this->paymentMethod
            );
        } catch (PaymentMethodDoesNotBelongToAccountException $exception) {
            throw new PaymentValidationException(
                message: __('messages.invalid_input'),
                errors: [$exception->getMessage()]
            );
        }
    }
}
