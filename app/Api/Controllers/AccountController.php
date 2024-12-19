<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\UpdateAccountAutopayStatusCommand;
use App\Api\Commands\UpdateAccountAutopayStatusHandler;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Requests\PatchAccountAutopayRequest;
use App\Api\Responses\GetSingleSuccessResponse;
use App\Api\Responses\SuccessResponse;
use App\Api\Traits\PaymentMethodExposeTrait;
use Aptive\Component\Http\HttpStatus;

class AccountController
{
    use PaymentMethodExposeTrait;

    /**
     * @param AccountRepository $accountRepository
     */
    public function __construct(private readonly AccountRepository $accountRepository)
    {
    }

    /**
     * @param string $accountId
     * @param PatchAccountAutopayRequest $request
     * @param UpdateAccountAutopayStatusHandler $handler
     *
     * @throws ResourceNotFoundException
     * @throws PaymentValidationException
     *
     * @return SuccessResponse
     */
    public function updateAutopayStatus(
        string $accountId,
        PatchAccountAutopayRequest $request,
        UpdateAccountAutopayStatusHandler $handler
    ): SuccessResponse {
        $handler->handle(command: UpdateAccountAutopayStatusCommand::fromRequest(request: $request));

        return SuccessResponse::create(
            message: __('messages.account.autopay.update_success'),
            selfLink: route(
                name: 'accounts.update-autopay-status',
                parameters: ['accountId' => $accountId]
            ),
            statusCode: HttpStatus::OK
        );
    }

    /**
     * @param string $accountId
     * @param PaymentMethodRepository $repository
     *
     * @throws \Throwable
     *
     * @return GetSingleSuccessResponse
     */
    public function primaryPaymentMethod(string $accountId, PaymentMethodRepository $repository): GetSingleSuccessResponse
    {
        $this->validateAccountExists(accountId: $accountId);
        $paymentMethod = $repository->findPrimaryForAccount(accountId: $accountId);

        throw_if(is_null($paymentMethod), new ResourceNotFoundException(message: __('messages.account.primary_payment_method_not_found')));

        return GetSingleSuccessResponse::create(
            entity: $this->exposePaymentMethod(paymentMethod: $paymentMethod),
            selfLink: route('payment-methods.find', ['paymentMethodId' => $paymentMethod->id]),
        );
    }

    /**
     * @param string $accountId
     * @param PaymentMethodRepository $repository
     *
     * @throws \Throwable
     *
     * @return GetSingleSuccessResponse
     */
    public function autopayPaymentMethod(string $accountId, PaymentMethodRepository $repository): GetSingleSuccessResponse
    {
        $this->validateAccountExists(accountId: $accountId);
        $paymentMethod = $repository->findAutopayMethodForAccount(accountId: $accountId);

        throw_if(is_null($paymentMethod), new ResourceNotFoundException(message: __('messages.account.autopay_payment_method_not_found')));

        return GetSingleSuccessResponse::create(
            entity: $this->exposePaymentMethod(paymentMethod: $paymentMethod),
            selfLink: route('payment-methods.find', ['paymentMethodId' => $paymentMethod->id]),
        );
    }

    /**
     * @param string $accountId
     *
     * @throws UnprocessableContentException
     */
    private function validateAccountExists(string $accountId): void
    {
        if (!$this->accountRepository->exists(id: $accountId)) {
            throw new UnprocessableContentException(message: __('messages.account.not_found'));
        }
    }
}
