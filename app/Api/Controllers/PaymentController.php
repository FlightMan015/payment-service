<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\AuthorizeAndCapturePaymentCommand;
use App\Api\Commands\AuthorizeAndCapturePaymentHandler;
use App\Api\Commands\AuthorizeAndCaptureSuspendedPaymentHandler;
use App\Api\Commands\AuthorizePaymentCommand;
use App\Api\Commands\AuthorizePaymentHandler;
use App\Api\Commands\CancelPaymentHandler;
use App\Api\Commands\CapturePaymentHandler;
use App\Api\Commands\CreateCheckPaymentCommand;
use App\Api\Commands\CreatePaymentHandler;
use App\Api\Commands\RefundPaymentCommand;
use App\Api\Commands\RefundPaymentHandler;
use App\Api\Commands\TerminatePaymentHandler;
use App\Api\Exceptions\InvalidPaymentStateException;
use App\Api\Exceptions\PaymentCancellationFailedException;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentRefundFailedException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException as ApiResourceNotFoundException;
use App\Api\Exceptions\ServerErrorException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Api\Requests\FilterRequest;
use App\Api\Requests\PatchPaymentRequest;
use App\Api\Requests\PaymentFilterRequest;
use App\Api\Requests\PostAuthorizeAndCapturePaymentRequest;
use App\Api\Requests\PostAuthorizePaymentRequest;
use App\Api\Requests\PostPaymentRequest;
use App\Api\Requests\PostRefundPaymentRequest;
use App\Api\Responses\AcceptedSuccessResponse;
use App\Api\Responses\CreatedSuccessResponse;
use App\Api\Responses\ErrorResponse;
use App\Api\Responses\GetMultipleSuccessResponse;
use App\Api\Responses\GetSingleSuccessResponse;
use App\Api\Responses\SuccessResponse;
use App\Helpers\DateTimeHelper;
use App\Jobs\FailedPaymentRefundsReportJob;
use App\Models\Payment;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\Services\Payment\Refund\RefundElectronicPaymentService;
use App\Services\Payment\Refund\RefundManualPaymentService;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PaymentController
{
    /**
     * @param PostPaymentRequest $request
     * @param CreatePaymentHandler $handler
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function create(PostPaymentRequest $request, CreatePaymentHandler $handler): Response
    {
        $paymentId = $handler->handle(createCheckPaymentCommand: CreateCheckPaymentCommand::fromRequest(request: $request));

        return CreatedSuccessResponse::create(
            message: __('messages.payment.created'),
            data: ['payment_id' => $paymentId],
            selfLink: $request->fullUrl()
        );
    }

    /**
     * Retrieve payments
     *
     * @param PaymentRepository $repository
     * @param PaymentFilterRequest $request
     *
     * @return JsonResponse
     */
    public function get(
        PaymentRepository $repository,
        PaymentFilterRequest $request
    ): JsonResponse {
        $paginator = $repository->filter(
            filter: $request->validatedWithCasts()->toArray(),
            columns: [
                'payments.id',
                'payment_status_id',
                'payments.amount',
                'payments.created_at',
            ],
        );
        $paginator->through(fn (Payment $payment) => $this->exposePayment(payment: $payment));

        return GetMultipleSuccessResponse::create(
            paginator: $paginator,
            request: $request,
        );
    }

    /**
     * Retrieve payment detail by given id
     *
     * @param string $paymentId
     * @param PaymentRepository $repository
     *
     * @throws ApiResourceNotFoundException
     *
     * @return GetSingleSuccessResponse
     */
    public function find(string $paymentId, PaymentRepository $repository): GetSingleSuccessResponse
    {
        $payment = $repository->find(paymentId: $paymentId, columns: [
            'id',
            'payment_status_id',
            'amount',
            'created_at',
        ]);

        return GetSingleSuccessResponse::create(
            entity: $this->exposePayment(payment: $payment),
            selfLink: route(name: 'payments.find', parameters: ['paymentId' => $paymentId]),
        );
    }

    /**
     * Update check/cash payment by given id
     *
     * @param string $paymentId
     * @param PatchPaymentRequest $request
     * @param PaymentRepository $repository
     *
     * @throws UnprocessableContentException
     * @throws ApiResourceNotFoundException
     *
     * @return SuccessResponse
     */
    public function update(
        string $paymentId,
        PatchPaymentRequest $request,
        PaymentRepository $repository
    ): SuccessResponse {
        $payment = $repository->findWithLedgerTypes(paymentId: $paymentId);
        $repository->update(payment: $payment, attributes: $request->validated());

        return SuccessResponse::create(
            message: __('messages.payment.updated'),
            selfLink: route(name: 'payments.update', parameters: ['paymentId' => $paymentId]),
        );
    }

    /**
     * @param string $paymentId
     * @param FilterRequest $request
     * @param PaymentRepository $paymentRepository
     * @param PaymentTransactionRepository $repository
     *
     * @throws ApiResourceNotFoundException if the payment is not found
     *
     * @return GetMultipleSuccessResponse
     */
    public function getTransactions(
        string $paymentId,
        FilterRequest $request,
        PaymentRepository $paymentRepository,
        PaymentTransactionRepository $repository
    ): GetMultipleSuccessResponse {
        $payment = $paymentRepository->find(paymentId: $paymentId);

        $paginator = $repository->filter(
            filter: array_merge($request->validated(), ['payment_id' => $payment->id]),
            columns: [
                'id',
                'payment_id',
                'payment_type_id',
                'gateway_transaction_id',
                'gateway_response_code',
                'created_at',
            ]
        );
        $paginator->through(fn (Transaction $transaction) => $this->exposeTransaction(transaction: $transaction));

        return GetMultipleSuccessResponse::create(paginator: $paginator, request: $request);
    }

    /**
     * @param string $paymentId
     * @param string $transactionId
     * @param PaymentRepository $paymentRepository
     * @param PaymentTransactionRepository $transactionRepository
     *
     * @throws ApiResourceNotFoundException
     *
     * @return GetSingleSuccessResponse
     */
    public function findTransaction(
        string $paymentId,
        string $transactionId,
        PaymentRepository $paymentRepository,
        PaymentTransactionRepository $transactionRepository,
    ): GetSingleSuccessResponse {
        $payment = $paymentRepository->find(paymentId: $paymentId);
        $transaction = $transactionRepository->find(
            paymentId: $payment->id,
            transactionId: $transactionId,
            columns: [
                'id',
                'payment_id',
                'transaction_type_id',
                'gateway_transaction_id',
                'gateway_response_code',
                'created_at',
            ]
        );

        return GetSingleSuccessResponse::create(
            entity: $this->exposeTransaction(transaction: $transaction),
            selfLink: route(
                name: 'payments.findTransaction',
                parameters: ['paymentId' => $payment->id, 'transactionId' => $transaction->id]
            ),
        );
    }

    private function exposePayment(Payment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'status' => $payment->status->name,
            'amount' => $payment->getDecimalAmount(),
            'created_at' => DateTimeHelper::formatDateTime($payment->created_at),
        ];
    }

    private function exposeTransaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'payment_id' => $transaction->payment->id,
            'transaction_type' => [
                'id' => $transaction->type->id,
                'name' => $transaction->type->name,
                'description' => $transaction->type->description,
            ],
            'gateway_transaction_id' => $transaction->gateway_transaction_id,
            'gateway_response_code' => $transaction->gateway_response_code ?: null,
            'created_at' => DateTimeHelper::formatDateTime($transaction->created_at),
        ];
    }

    /**
     * @param PostAuthorizePaymentRequest $request
     * @param AuthorizePaymentHandler $handler
     *
     * @throws \Throwable
     *
     * @return SuccessResponse|ErrorResponse
     */
    public function authorize(
        PostAuthorizePaymentRequest $request,
        AuthorizePaymentHandler $handler
    ): ErrorResponse|SuccessResponse {
        $result = $handler->handle(command: AuthorizePaymentCommand::fromRequest(request: $request));

        if ($result->status === PaymentStatusEnum::DECLINED) {
            return $this->declinedPaymentResponse(
                errorMessage: __('messages.operation.authorization.gateway_error', ['message' => $result->message])
            );
        }

        return SuccessResponse::create(
            message: __('messages.success'),
            selfLink: $request->fullUrl(),
            additionalData: [
                'status' => $result->status->name,
                'payment_id' => $result->paymentId,
                'transaction_id' => $result->transactionId,
                'message' => $result->message,
            ]
        );
    }

    /**
     * @param string $paymentId
     * @param Request $request
     * @param CapturePaymentHandler $handler
     *
     * @throws ServerErrorException
     * @throws PaymentValidationException
     * @throws \Exception
     *
     * @return SuccessResponse
     */
    public function capture(
        string $paymentId,
        Request $request,
        CapturePaymentHandler $handler
    ): SuccessResponse {
        $result = $handler->handle(paymentId: $paymentId);

        return SuccessResponse::create(
            message: __('messages.payment.processed'),
            selfLink: $request->fullUrl(),
            statusCode: HttpStatus::OK,
            additionalData: [
                'status' => $result->status->name,
                'payment_id' => $paymentId,
                'transaction_id' => $result->transactionId,
                'message' => $result->message,
            ]
        );
    }

    /**
     * @param PostAuthorizeAndCapturePaymentRequest $request
     * @param AuthorizeAndCapturePaymentHandler $handler
     *
     * @throws BindingResolutionException
     * @throws ServerErrorException
     * @throws \Throwable
     *
     * @return ErrorResponse|SuccessResponse
     */
    public function authorizeAndCapture(
        PostAuthorizeAndCapturePaymentRequest $request,
        AuthorizeAndCapturePaymentHandler $handler
    ): ErrorResponse|SuccessResponse {
        $result = $handler->handle(command: AuthorizeAndCapturePaymentCommand::fromRequest(request: $request));

        if ($result->status === PaymentStatusEnum::DECLINED) {
            return $this->declinedPaymentResponse(
                errorMessage: __('messages.operation.authorization_and_capture.gateway_error', ['message' => $result->message])
            );
        }

        return SuccessResponse::create(
            message: __('messages.payment.authorized_and_captured'),
            selfLink: $request->fullUrl(),
            additionalData: [
                'status' => $result->status->name,
                'payment_id' => $result->paymentId,
                'transaction_id' => $result->transactionId,
                'message' => $result->message ?? __('messages.payment.authorized_and_captured'),
            ]
        );
    }

    /**
     * @param string $paymentId
     * @param PostRefundPaymentRequest $request
     * @param RefundPaymentHandler $handler
     *
     * @throws ApiResourceNotFoundException
     * @throws InvalidPaymentStateException
     * @throws PaymentRefundFailedException
     * @throws PaymentProcessingValidationException
     * @throws \Throwable
     *
     * @return SuccessResponse|ErrorResponse
     */
    public function electronicRefund(
        string $paymentId,
        PostRefundPaymentRequest $request,
        RefundPaymentHandler $handler
    ): ErrorResponse|SuccessResponse {
        $result = $handler->handle(
            refundPaymentCommand: RefundPaymentCommand::fromRequest(request: $request, paymentId: $paymentId),
            refundPaymentService: app(RefundElectronicPaymentService::class)
        );

        if ($result->status === PaymentStatusEnum::DECLINED) {
            return $this->declinedPaymentResponse(
                errorMessage: __('messages.operation.refund.gateway_error', ['message' => $result->errorMessage])
            );
        }

        return SuccessResponse::create(
            message: __('messages.payment.refunded'),
            selfLink: $request->fullUrl(),
            additionalData: [
                'status' => $result->status->name,
                'refund_payment_id' => $result->refundPaymentId,
                'transaction_id' => $result->transactionId,
            ]
        );
    }

    /**
     * @param string $paymentId
     * @param PostRefundPaymentRequest $request
     * @param RefundPaymentHandler $handler
     *
     * @throws ApiResourceNotFoundException
     * @throws InvalidPaymentStateException
     * @throws PaymentRefundFailedException
     * @throws PaymentProcessingValidationException
     * @throws \Throwable
     *
     * @return SuccessResponse|ErrorResponse
     */
    public function manualRefund(
        string $paymentId,
        PostRefundPaymentRequest $request,
        RefundPaymentHandler $handler
    ): ErrorResponse|SuccessResponse {
        $result = $handler->handle(
            refundPaymentCommand: RefundPaymentCommand::fromRequest(request: $request, paymentId: $paymentId),
            refundPaymentService: app(RefundManualPaymentService::class)
        );

        return SuccessResponse::create(
            message: __('messages.payment.refunded'),
            selfLink: $request->fullUrl(),
            additionalData: [
                'status' => $result->status->name,
                'refund_payment_id' => $result->refundPaymentId,
                'transaction_id' => $result->transactionId,
            ]
        );
    }

    /**
     * @param string $paymentId
     * @param Request $request
     * @param CancelPaymentHandler $handler
     *
     * @throws ApiResourceNotFoundException
     * @throws InvalidPaymentStateException
     * @throws PaymentCancellationFailedException
     * @throws \Throwable
     *
     * @return SuccessResponse
     */
    public function cancel(string $paymentId, Request $request, CancelPaymentHandler $handler): SuccessResponse
    {
        $result = $handler->handle(paymentId: $paymentId);

        return SuccessResponse::create(
            message: __('messages.payment.cancelled'),
            selfLink: $request->fullUrl(),
            statusCode: HttpStatus::OK,
            additionalData: [
                'status' => $result->status->name,
                'payment_id' => $paymentId,
                'transaction_id' => $result->transactionId,
            ]
        );
    }

    /**
     * @param string $paymentId
     * @param Request $request
     * @param AuthorizeAndCaptureSuspendedPaymentHandler $handler
     * @param PaymentRepository $paymentRepository
     *
     * @throws ApiResourceNotFoundException
     * @throws BindingResolutionException
     * @throws PaymentValidationException
     * @throws \Throwable
     * @throws UnsupportedValueException
     *
     * @return SuccessResponse
     */
    public function processSuspended(
        string $paymentId,
        Request $request,
        AuthorizeAndCaptureSuspendedPaymentHandler $handler,
        PaymentRepository $paymentRepository
    ): SuccessResponse {
        $payment = $paymentRepository->find(paymentId: $paymentId, relations: ['status']);
        $result = $handler->handle(new AuthorizeAndCapturePaymentCommand(
            amount: $payment->amount,
            accountId: $payment->account_id,
            paymentMethodId: $payment->payment_method_id,
            notes: $payment->notes,
            paymentId: $paymentId
        ));

        return SuccessResponse::create(
            message: __('messages.payment.processed'),
            selfLink: $request->fullUrl(),
            statusCode: HttpStatus::OK,
            additionalData: [
                'status' => $result->status->name,
                'payment_id' => $paymentId,
                'transaction_id' => $result->transactionId,
            ]
        );
    }

    /**
     * @return AcceptedSuccessResponse
     */
    public function failedRefundsReport(): AcceptedSuccessResponse
    {
        FailedPaymentRefundsReportJob::dispatch();

        return AcceptedSuccessResponse::create(
            message: __(key: 'messages.reports.failed_refunds.started'),
            selfLink: route(name: 'payments.failed-refunds-report'),
        );
    }

    /**
     * @param string $paymentId
     * @param TerminatePaymentHandler $terminatePaymentHandler
     *
     * @throws ApiResourceNotFoundException
     * @throws UnprocessableContentException
     *
     * @return SuccessResponse
     */
    public function terminate(
        string $paymentId,
        TerminatePaymentHandler $terminatePaymentHandler,
    ): SuccessResponse {
        $payment = $terminatePaymentHandler->handle($paymentId);

        return SuccessResponse::create(
            message: __('messages.payment.terminated', ['id' => $payment->id]),
            selfLink: route(name: 'payments.find', parameters: ['paymentId' => $paymentId]),
            additionalData: ['payment_id' => $paymentId]
        );
    }

    private function declinedPaymentResponse(string $errorMessage): ErrorResponse
    {
        return new ErrorResponse(
            data: ['_metadata' => ['success' => false], 'result' => ['message' => $errorMessage]],
            status: HttpStatus::UNPROCESSABLE_ENTITY
        );
    }
}
