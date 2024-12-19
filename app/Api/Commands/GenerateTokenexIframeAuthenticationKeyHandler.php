<?php

declare(strict_types=1);

namespace App\Api\Commands;

use Aptive\Component\Http\Exceptions\InternalServerErrorHttpException;

class GenerateTokenexIframeAuthenticationKeyHandler
{
    /**
     * @param GenerateTokenexIframeAuthenticationKeyCommand $command
     *
     * @throws InternalServerErrorHttpException
     *
     * @return string
     */
    public function handle(GenerateTokenexIframeAuthenticationKeyCommand $command): string
    {
        $this->validateTokenexConfig();

        $timestamp = $command->timestamp;
        $origins = implode(separator: ',', array: $command->origins);

        return $this->generateHMAC(
            concatenatedInfo: sprintf(
                '%s|%s|%s|%s',
                config(key: 'tokenex.iframe_tokenex_id'),
                $origins,
                $timestamp,
                $command->tokenScheme
            )
        );
    }

    /**
     * @throws InternalServerErrorHttpException
     */
    private function validateTokenexConfig(): void
    {
        if (!config(key: 'tokenex.iframe_tokenex_id') || !config(key: 'tokenex.iframe_client_secret_key')) {
            throw new InternalServerErrorHttpException(message: __('messages.tokenex.config_not_set_properly'));
        }
    }

    private function generateHMAC(string $concatenatedInfo): string
    {
        $hmac = hash_hmac(
            algo: 'sha256',
            data: $concatenatedInfo,
            key: config(key: 'tokenex.iframe_client_secret_key'),
            binary: true
        );

        return base64_encode(string: $hmac);
    }
}
