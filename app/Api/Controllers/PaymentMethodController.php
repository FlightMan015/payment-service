<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\CreatePaymentMethodCommand;
use App\Api\Commands\CreatePaymentMethodHandler;
use App\Api\Commands\DeletePaymentMethodHandler;
use App\Api\Commands\UpdatePaymentMethodCommand;
use App\Api\Commands\UpdatePaymentMethodHandler;
use App\Api\Commands\ValidatePaymentMethodCommand;
use App\Api\Commands\ValidatePaymentMethodHandler;
use App\Api\Exceptions\InvalidPaymentMethodStateException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\ServerErrorException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Requests\PatchPaymentMethodRequest;
use App\Api\Requests\PaymentMethodFilterRequest;
use App\Api\Requests\PostPaymentMethodRequest;
use App\Api\Responses\CreatedSuccessResponse;
use App\Api\Responses\GetMultipleSuccessResponse;
use App\Api\Responses\GetSingleSuccessResponse;
use App\Api\Responses\SuccessResponse;
use App\Api\Traits\PaymentMethodExposeTrait;
use App\Models\PaymentMethod;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Contracts\Container\BindingResolutionException;

class PaymentMethodController
{
    use PaymentMethodExposeTrait;

    /**
     * Retrieve payment methods
     *
     * @param PaymentMethodRepository $repository
     * @param PaymentMethodFilterRequest $request
     *
     * @return GetMultipleSuccessResponse
     */
    public function get(
        PaymentMethodRepository $repository,
        PaymentMethodFilterRequest $request
    ): GetMultipleSuccessResponse {
        $paginator = $repository->filter(
            filter: $request->validated(),
            columns: [
                'id',
                'account_id',
                'payment_type_id',
                'created_at',
                'payment_gateway_id',
                'payment_type_id',
                'last_four',
                'ach_routing_number',
                'ach_account_type',
                'ach_bank_name',
                'cc_type',
                'cc_expiration_month',
                'cc_expiration_year',
                'is_primary',
            ],
            withRelations: [
                'gateway:id,name',
                'type:id,name',
                'ledger:account_id,autopay_payment_method_id'
            ],
        );
        $paginator->through(fn (PaymentMethod $paymentMethod) => $this->exposePaymentMethod(paymentMethod: $paymentMethod));

        return GetMultipleSuccessResponse::create(
            paginator: $paginator,
            request: $request,
        );
    }

    /**
     * Retrieve payment method detail by given id
     *
     * @param string $paymentMethodId
     * @param PaymentMethodRepository $repository
     *
     * @throws ResourceNotFoundException
     *
     * @return GetSingleSuccessResponse
     */
    public function find(string $paymentMethodId, PaymentMethodRepository $repository): GetSingleSuccessResponse
    {
        $paymentMethod = $repository->find(
            paymentMethodId: $paymentMethodId,
            columns: [
                'id',
                'account_id',
                'payment_type_id',
                'created_at',
                'payment_gateway_id',
                'payment_type_id',
                'last_four',
                'ach_routing_number',
                'ach_account_type',
                'ach_bank_name',
                'cc_type',
                'cc_expiration_month',
                'cc_expiration_year',
                'is_primary',
            ],
        );

        return GetSingleSuccessResponse::create(
            entity: $this->exposePaymentMethod(paymentMethod: $paymentMethod),
            selfLink: route('payment-methods.find', ['paymentMethodId' => $paymentMethodId]),
        );
    }

    /**
     * @param PostPaymentMethodRequest $request
     * @param CreatePaymentMethodHandler $handler
     *
     * @throws ServerErrorException
     * @throws UnprocessableContentException
     * @throws \Throwable
     *
     * @return CreatedSuccessResponse
     */
    public function create(PostPaymentMethodRequest $request, CreatePaymentMethodHandler $handler): CreatedSuccessResponse
    {
        $result = $handler->handle(command: CreatePaymentMethodCommand::fromRequest(request: $request));

        return CreatedSuccessResponse::create(
            message: __('messages.payment_method.created'),
            data: ['payment_method_id' => $result->paymentMethodId],
            selfLink: route(name: 'payment-methods.find', parameters: $result->paymentMethodId)
        );
    }

    /**
     * @param string $paymentMethodId
     * @param ValidatePaymentMethodHandler $handler
     * @param PaymentMethodRepository $repository
     *
     * @throws BindingResolutionException
     * @throws ResourceNotFoundException
     * @throws \Throwable
     *
     * @return SuccessResponse
     */
    public function validate(
        string $paymentMethodId,
        ValidatePaymentMethodHandler $handler,
        PaymentMethodRepository $repository
    ): SuccessResponse {
        $paymentMethod = $repository->find(paymentMethodId: $paymentMethodId);

        $validationResult = $handler->handle(command: new ValidatePaymentMethodCommand(paymentMethod: $paymentMethod));

        return SuccessResponse::create(
            message: __('messages.payment_method.validated'),
            selfLink: route(name: 'payment-methods.find', parameters: $paymentMethodId),
            statusCode: HttpStatus::OK,
            additionalData: $validationResult->toArray(),
        );
    }

    /**
     * @param string $paymentMethodId
     * @param PatchPaymentMethodRequest $request
     * @param PaymentMethodRepository $repository
     * @param UpdatePaymentMethodHandler $handler
     *
     * @throws BindingResolutionException
     * @throws ResourceNotFoundException
     * @throws UnprocessableContentException
     * @throws PaymentValidationException
     * @throws UnsupportedValueException
     * @throws \Throwable
     *
     * @return SuccessResponse
     */
    public function update(
        string $paymentMethodId,
        PatchPaymentMethodRequest $request,
        PaymentMethodRepository $repository,
        UpdatePaymentMethodHandler $handler
    ): SuccessResponse {
        $paymentMethod = $repository->find(paymentMethodId: $paymentMethodId);

        $handler->handle(
            paymentMethod: $paymentMethod,
            command: UpdatePaymentMethodCommand::fromRequest($request)
        );

        return SuccessResponse::create(
            message: __('messages.payment_method.updated'),
            selfLink: route(name: 'payment-methods.update', parameters: $paymentMethodId),
        );
    }

    /**
     * @param string $paymentMethodId
     * @param PaymentMethodRepository $repository
     * @param DeletePaymentMethodHandler $handler
     *
     * @throws InvalidPaymentMethodStateException
     * @throws ResourceNotFoundException
     *
     * @return SuccessResponse
     */
    public function delete(
        string $paymentMethodId,
        PaymentMethodRepository $repository,
        DeletePaymentMethodHandler $handler
    ): SuccessResponse {
        $paymentMethod = $repository->find(paymentMethodId: $paymentMethodId);

        $handler->handle(paymentMethod: $paymentMethod);

        return SuccessResponse::create(
            message: __('messages.payment_method.deleted'),
            selfLink: route(name: 'payment-methods.find', parameters: $paymentMethodId),
        );
    }
}
