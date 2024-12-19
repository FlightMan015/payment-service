<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\UpdateSubscriptionAutopayStatusCommand;
use App\Api\Commands\UpdateSubscriptionAutopayStatusHandler;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Requests\PatchSubscriptionAutopayStatusRequest;
use App\Api\Responses\SuccessResponse;

class SubscriptionController
{
    /**
     * @param string $subscriptionId
     * @param PatchSubscriptionAutopayStatusRequest $request
     * @param UpdateSubscriptionAutopayStatusHandler $handler
     *
     * @throws ResourceNotFoundException
     * @throws PaymentValidationException
     *
     * @return SuccessResponse
     */
    public function updateAutopayStatus(
        string $subscriptionId,
        PatchSubscriptionAutopayStatusRequest $request,
        UpdateSubscriptionAutopayStatusHandler $handler
    ): SuccessResponse {
        $handler->handle(command: UpdateSubscriptionAutopayStatusCommand::fromRequest(request: $request));

        return SuccessResponse::create(
            message: __('messages.subscription.autopay.update_success'),
            selfLink: route(
                name: 'subscriptions.update-autopay-status',
                parameters: [
                    'subscriptionId' => $subscriptionId
                ]
            )
        );
    }
}
