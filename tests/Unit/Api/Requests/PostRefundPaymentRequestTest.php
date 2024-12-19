<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostRefundPaymentRequest;
use Tests\Helpers\AbstractApiRequestTest;

class PostRefundPaymentRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PostRefundPaymentRequest
     */
    public function getTestedRequest(): PostRefundPaymentRequest
    {
        return new PostRefundPaymentRequest();
    }

    /**
     * @inheritDoc
     */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'non-int amount' => [
            ['amount' => 'amount']
        ];
        yield 'non-int amount 2' => [
            ['amount' => [123]]
        ];
        yield 'zero amount' => [
            ['amount' => 0]
        ];
        yield 'negative amount' => [
            ['amount' => -1]
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getValidData(): array|\Iterator
    {
        yield 'empty request' => [
            []
        ];
        yield 'null amount' => [
            ['amount' => null]
        ];
        yield 'positive amount' => [
            ['amount' => 1]
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getValidDataSet(): array
    {
        return [];
    }
}
