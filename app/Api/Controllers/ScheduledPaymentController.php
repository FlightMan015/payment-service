<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\CancelScheduledPaymentHandler;
use App\Api\Commands\CreateScheduledPaymentCommand;
use App\Api\Commands\CreateScheduledPaymentHandler;
use App\Api\Exceptions\PaymentCancellationFailedException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Requests\PostScheduledPaymentRequest;
use App\Api\Responses\CreatedSuccessResponse;
use App\Api\Responses\SuccessResponse;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ScheduledPaymentController
{
    /**
     * @param PostScheduledPaymentRequest $request
     * @param CreateScheduledPaymentHandler $handler
     *
     * @return Response
     */
    public function create(PostScheduledPaymentRequest $request, CreateScheduledPaymentHandler $handler): Response
    {
        $scheduledPaymentId = $handler->handle(
            createScheduledPaymentCommand: CreateScheduledPaymentCommand::fromRequest(
                request: $request
            )
        );

        return CreatedSuccessResponse::create(
            message: __('messages.scheduled_payment.created'),
            data: ['scheduled_payment_id' => $scheduledPaymentId],
            selfLink: $request->fullUrl()
        );
    }

    /**
     * @param string $scheduledPaymentId
     * @param Request $request
     * @param CancelScheduledPaymentHandler $handler
     *
     * @throws ResourceNotFoundException
     * @throws PaymentCancellationFailedException
     *
     * @return SuccessResponse
     */
    public function cancel(string $scheduledPaymentId, Request $request, CancelScheduledPaymentHandler $handler): SuccessResponse
    {
        $result = $handler->handle(scheduledPaymentId: $scheduledPaymentId);

        return SuccessResponse::create(
            message: __('messages.scheduled_payment.cancelled'),
            selfLink: $request->fullUrl(),
            statusCode: HttpStatus::OK,
            additionalData: [
                'status' => $result->status->name,
                'scheduled_payment_id' => $scheduledPaymentId,
            ]
        );
    }
}
