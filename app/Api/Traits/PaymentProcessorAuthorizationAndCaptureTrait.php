<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\UnprocessableContentException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

trait PaymentProcessorAuthorizationAndCaptureTrait
{
    private PaymentProcessorResultDto $operationResult;

    /**
     * @throws PaymentProcessingValidationException
     * @throws UnprocessableContentException
     * @throws Throwable
     */
    private function callPaymentProcessorAuthorizationAndCapture(): void
    {
        try {
            $this->operationResult = $this->paymentProcessorRepository->authorizeAndCapture(
                paymentProcessor: $this->paymentProcessor,
                payment: $this->payment,
                gateway: $this->gateway
            );
        } catch (OperationValidationException $exception) {
            throw new PaymentProcessingValidationException(message: $exception->getMessage(), context: $this->command->toArray());
        } catch (\Exception $ex) {
            Log::error(message: $ex->getMessage(), context: ['request' => $this->command->toArray()]);

            throw new UnprocessableContentException(
                __('messages.operation.authorization_and_capture.unexpected_error', ['message' => $ex->getMessage()])
            );
        }
    }
}
