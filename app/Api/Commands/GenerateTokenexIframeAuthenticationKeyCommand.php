<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostTokenexGenerateAuthenticationKeyRequest;

final class GenerateTokenexIframeAuthenticationKeyCommand
{
    /**
     * @param string $tokenScheme
     * @param array $origins
     * @param string $timestamp - UTC date in the format of YmdHis
     */
    public function __construct(
        public readonly string $tokenScheme,
        public readonly array $origins,
        public readonly string $timestamp
    ) {
    }

    /**
     * @param PostTokenexGenerateAuthenticationKeyRequest $request
     *
     * @return self
     */
    public static function fromRequest(PostTokenexGenerateAuthenticationKeyRequest $request): self
    {
        return new self(
            tokenScheme: $request->token_scheme,
            origins: $request->origins,
            timestamp: (string)$request->timestamp
        );
    }
}
