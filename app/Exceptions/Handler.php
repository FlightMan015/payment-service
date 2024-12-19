<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Api\Exceptions\AbstractAPIException;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\ServerErrorException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Responses\ErrorResponse as CustomerErrorResponse;
use App\Instrumentation\Datadog\Instrument;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use Aptive\Component\Http\Exceptions\BadRequestHttpException;
use Aptive\Component\Http\Exceptions\NotFoundHttpException as AptiveComponentNotFoundHttpException;
use Aptive\Component\Http\Exceptions\NotImplementedHttpException;
use Aptive\Component\Http\HttpStatus;
use Aptive\Illuminate\Http\JsonApi\ErrorResponse;
use Aptive\PestRoutesSDK\Exceptions\ResourceNotFoundException as PestroutesSDKResourceNotFoundException;
use Assert\AssertionFailedException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as SymphonyNotFoundHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException as SymphonyResourceNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];
    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Renders an exception as an HTTP response
     *
     * @param mixed $request
     * @param \Throwable $exception
     */
    public function render($request, \Throwable $exception)
    {
        // 422
        if ($exception instanceof AssertionFailedException) {
            $this->noticeException(exception: $exception);
            return ErrorResponse::fromException($request, $exception, HttpStatus::UNPROCESSABLE_ENTITY);
        }

        // 404 Errors
        if ($exception instanceof SymphonyNotFoundHttpException
            || $exception instanceof AptiveComponentNotFoundHttpException
            || $exception instanceof PestroutesSDKResourceNotFoundException
            || $exception instanceof SymphonyResourceNotFoundException
        ) {
            $this->noticeException(exception: $exception);
            return ErrorResponse::fromException($request, $exception, HttpStatus::NOT_FOUND);
        }

        // 400 Errors
        if ($exception instanceof ValidationException) {
            $this->noticeException(exception: $exception);
            return ErrorResponse::fromException($request, $exception, HttpStatus::BAD_REQUEST);
        }

        // 405 Method Isn't Allowed
        if ($exception instanceof MethodNotAllowedHttpException) {
            $this->noticeException(exception: $exception);
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::METHOD_NOT_ALLOWED,
                errorMessage: $exception->getMessage(),
            );
        }

        if ($exception instanceof BadRequestHttpException) {
            $this->noticeException(exception: $exception);
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::BAD_REQUEST,
                errorMessage: $exception->getMessage(),
            );
        }

        // API
        if ($exception instanceof AbstractAPIException) {
            return $this->handleAPIException(exception: $exception);
        }

        if ($exception instanceof ThrottleRequestsException) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::TOO_MANY_REQUESTS,
            );
        }

        // 422 errors
        if ($exception instanceof InvalidOperationException
            || $exception instanceof AbstractUnprocessableScheduledPaymentException
        ) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::UNPROCESSABLE_ENTITY,
                errorMessage: $exception->getMessage()
            );
        }

        // 501 Errors
        if ($exception instanceof NotImplementedHttpException) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: NotImplementedHttpException::STATUS_CODE,
            );
        }

        // 500 Errors (any other error that we have not already dealt with)
        Log::error(message: $exception->getMessage(), context: ['trace' => $exception->getTrace()]);
        Instrument::error($exception);

        return CustomerErrorResponse::fromException(
            exception: $exception,
            status: HttpStatus::INTERNAL_SERVER_ERROR,
            errorMessage: $exception->getMessage(),
        );
    }

    private function handleAPIException(\Throwable $exception): CustomerErrorResponse
    {
        if ($exception instanceof ResourceNotFoundException) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::NOT_FOUND,
            );
        }

        if ($exception instanceof PaymentValidationException) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::BAD_REQUEST,
                errorMessage: $exception->getMessage(),
                errors: $exception->getErrors(),
            );
        }
        if ($exception instanceof UnsupportedValueException) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::UNPROCESSABLE_ENTITY
            );
        }

        if ($exception instanceof ServerErrorException) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::INTERNAL_SERVER_ERROR,
                errorMessage: $exception->getMessage(),
                errors: $exception->errors,
            );
        }

        if ($exception instanceof PaymentProcessingValidationException) {
            Log::warning(message: __('messages.payment.processing_validation_error'), context: [
                'message' => $exception->getMessage(),
                'context' => $exception->context,
            ]);

            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::UNPROCESSABLE_ENTITY,
                errorMessage: $exception->getMessage()
            );
        }

        if ($exception instanceof UnprocessableContentException) {
            return CustomerErrorResponse::fromException(
                exception: $exception,
                status: HttpStatus::UNPROCESSABLE_ENTITY,
                errorMessage: $exception->getMessage()
            );
        }

        return CustomerErrorResponse::fromException(
            exception: $exception,
            status: HttpStatus::INTERNAL_SERVER_ERROR,
            errorMessage: $exception->getMessage()
        );
    }

    private function noticeException(\Throwable $exception): void
    {
        Log::notice(message: $exception->getMessage(), context: ['trace' => $exception->getTrace()]);
    }
}
