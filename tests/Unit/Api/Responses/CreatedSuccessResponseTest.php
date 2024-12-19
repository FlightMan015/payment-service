<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Responses;

use App\Api\Responses\CreatedSuccessResponse;
use Aptive\Component\Http\HttpStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class CreatedSuccessResponseTest extends UnitTestCase
{
    #[Test]
    public function create_method_generates_response_as_expected(): void
    {
        $message = 'Resource created successfully';
        $data = ['foo' => 'bar'];
        $selfLink = 'https://example.com/resource/123';

        $response = CreatedSuccessResponse::create($message, $data, $selfLink);

        $expectedResponseData = [
            '_metadata' => [
                'success' => true,
                'links' => [
                    'self' => $selfLink,
                ],
            ],
            'result' => [
                'message' => $message,
                'foo' => 'bar',
            ],
        ];

        $this->assertEquals(expected: $expectedResponseData, actual: $response->getData(assoc: true));
        $this->assertSame(expected: HttpStatus::CREATED, actual: $response->getStatusCode());
    }
}
