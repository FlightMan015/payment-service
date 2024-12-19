<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\GenerateTokenexIframeAuthenticationKeyCommand;
use App\Api\Commands\GenerateTokenexIframeAuthenticationKeyHandler;
use App\Api\Commands\UpdateTokenexPaymentAccountsHandler;
use App\Api\Requests\PostTokenexGenerateAuthenticationKeyRequest;
use App\Api\Responses\AcceptedSuccessResponse;
use App\Api\Responses\SuccessResponse;
use Aptive\Component\Http\Exceptions\InternalServerErrorHttpException;

class TokenexGatewayController
{
    /**
     * @param PostTokenexGenerateAuthenticationKeyRequest $request
     * @param GenerateTokenexIframeAuthenticationKeyHandler $handler
     *
     * @throws InternalServerErrorHttpException
     *
     * @return SuccessResponse
     */
    public function generateAuthenticationKey(
        PostTokenexGenerateAuthenticationKeyRequest $request,
        GenerateTokenexIframeAuthenticationKeyHandler $handler
    ): SuccessResponse {
        return SuccessResponse::create(
            message: __('messages.tokenex.key_generated'),
            selfLink: route(name: 'gateways.tokenex.generate-authentication-key'),
            additionalData: [
                'authentication_key' => $handler->handle(
                    command: GenerateTokenexIframeAuthenticationKeyCommand::fromRequest($request)
                ),
            ]
        );
    }

    /**
     * @param UpdateTokenexPaymentAccountsHandler $handler
     *
     * @return AcceptedSuccessResponse
     */
    public function updateAccounts(UpdateTokenexPaymentAccountsHandler $handler): AcceptedSuccessResponse
    {
        $handler->handle();

        return AcceptedSuccessResponse::create(
            message: __('messages.tokenex.start_account_updating'),
            selfLink: route(name: 'gateways.tokenex.update-accounts')
        );
    }
}
