<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\ValidateCreditCardTokenCommand;
use App\Api\Commands\ValidateCreditCardTokenHandler;
use App\Api\Exceptions\MissingGatewayException;
use App\Api\Requests\PostValidateCreditCardTokenRequest;
use App\Api\Responses\SuccessResponse;
use Aptive\Component\Http\HttpStatus;

class CreditCardController
{
    /**
     * @param PostValidateCreditCardTokenRequest $request
     * @param ValidateCreditCardTokenHandler $handler
     *
     * @throws MissingGatewayException
     * @throws \Throwable
     *
     * @return SuccessResponse
     */
    public function validate(PostValidateCreditCardTokenRequest $request, ValidateCreditCardTokenHandler $handler): SuccessResponse
    {
        return SuccessResponse::create(
            message: __('messages.credit_card.validate.success'),
            selfLink: route(name: 'credit-cards.validate'),
            statusCode: HttpStatus::OK,
            additionalData: $handler->handle(command: ValidateCreditCardTokenCommand::fromRequest($request))->toArray(),
        );
    }
}
