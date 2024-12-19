<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostTokenexGenerateAuthenticationKeyRequest;
use App\PaymentProcessor\Enums\TokenexTokenSchemeEnum;
use Tests\Helpers\AbstractApiRequestTest;

class PostTokenexGenerateAuthenticationKeyRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PostTokenexGenerateAuthenticationKeyRequest
     */
    public function getTestedRequest(): PostTokenexGenerateAuthenticationKeyRequest
    {
        return new PostTokenexGenerateAuthenticationKeyRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'empty request body' => [
            'data' => [
                // empty
            ],
        ];
        yield 'missing required token_scheme' => [
            'data' => [
                // Missing token scheme
                'origins' => [
                    'https://mysite.com',
                    'https://someothersite.com'
                ],
                'timestamp' => '20180109161437'
            ],
        ];
        yield 'token scheme is not an acceptable value' => [
            'data' => [
                'token_scheme' => 'NotAcceptableValue',
                'origins' => [
                    'https://mysite.com',
                    'https://someothersite.com'
                ],
                'timestamp' => '20180109161437'
            ],
        ];
        yield 'missing required origins' => [
            'data' => [
                'token_scheme' => TokenexTokenSchemeEnum::PCI->value,
                // Missing required origins
                'timestamp' => '20180109161437'
            ],
        ];
        yield 'origins is not an array' => [
            'data' => [
                'token_scheme' => TokenexTokenSchemeEnum::PCI->value,
                'origins' => 'NotAnArray',
                'timestamp' => '20180109161437'
            ],
        ];
        yield 'origins array has values that dont start with https or http' => [
            'data' => [
                'token_scheme' => TokenexTokenSchemeEnum::PCI->value,
                'origins' => [
                    'stp://somesite.com' // stp is not http or https
                ],
                'timestamp' => '20180109161437'
            ],
        ];
        yield 'missing required timestamp' => [
            'data' => [
                'token_scheme' => TokenexTokenSchemeEnum::PCI->value,
                'origins' => [
                    'https://mysite.com',
                    'https://someothersite.com'
                ],
                // Missing required timestamp
            ],
        ];
        yield 'timestamp must be a date with format YmdHis' => [
            'data' => [
                'token_scheme' => TokenexTokenSchemeEnum::PCI->value,
                'origins' => [
                    'https://mysite.com',
                    'https://someothersite.com'
                ],
                'timestamp' => '2023-02-01 09:05:06' // Incorrect format
            ],
        ];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        yield 'valid data with full input' => [
            'data' => self::getValidDataSet()
        ];
    }

    public static function getValidDataSet(): array
    {
        return [
            'token_scheme' => TokenexTokenSchemeEnum::PCI->value,
            'origins' => [
                'https://mysite.com',
                'http://someothersite.com'
            ],
            'timestamp' => '20180109161437' // UTC timestamp in format: yyyyMMddHHmmss but this is how that format is represented in php datetime: YmdHis
        ];
    }
}
