<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Responses\ErrorResponse as CustomerErrorResponse;
use Aptive\Component\Http\Exceptions\NotFoundHttpException;
use Aptive\Component\Http\HttpStatus;
use Aptive\Component\JsonApi\JsonApi;
use Aptive\Illuminate\Http\JsonApi\ErrorResponse;
use Assert\InvalidArgumentException;
use DomainException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\Unit\UnitTestCase;

class ErrorHandlingTest extends UnitTestCase
{
    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        JsonApi::config(['debug' => false]);
        $this->handler = $this->app->make(ExceptionHandler::class);
    }

    #[Test]
    public function it_returns_expected_response_if_instance_of_assertion_failed_exception_is_thrown(): void
    {
        // InvalidArgumentException implements AssertionFailedException
        $exception = new InvalidArgumentException('Unprocessable entity', HttpStatus::UNPROCESSABLE_ENTITY);

        $response = $this->handler->render(request(), $exception);

        $this->assertExceptionRenderedResponse($exception, $response, true);
    }

    #[Test]
    public function it_returns_expected_response_if_http_exception_is_thrown(): void
    {
        $exception = new NotFoundHttpException('Company was not found');

        $response = $this->handler->render(request(), $exception);

        $this->assertExceptionRenderedResponse($exception, $response);
    }

    #[Test]
    public function it_returns_expected_response_if_domain_exception_is_thrown(): void
    {
        $exception = new DomainException('contact has not been loaded', 500);

        $response = $this->handler->render(request(), $exception);

        $expectedErrors = [
            '_metadata' => [
                'success' => false,
            ],
            'result' => [
                'message' => 'contact has not been loaded',
            ],
        ];

        $this->assertInstanceOf(CustomerErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors, JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    #[Test]
    public function it_returns_expected_response_if_validation_exception_is_thrown(): void
    {
        $exception = ValidationException::withMessages(['the account_id is missing']);
        $exception->status = HttpStatus::BAD_REQUEST;

        $expectedErrors = [
            [
                'status' => (string)$exception->status,
                'title' => HttpStatus::toMessage($exception->status),
                'detail' => $exception->getMessage(),
            ]
        ];

        $response = $this->handler->render(request(), $exception);

        $this->assertInstanceOf(ErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['errors' => $expectedErrors], JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    #[Test]
    public function it_returns_expected_response_if_throttle_limit_is_thrown(): void
    {
        $exception = new ThrottleRequestsException('Too Many Attempts.');

        $expectedErrors = [
            '_metadata' => [
                'success' => false,
            ],
            'result' => [
                'message' => 'Too Many Attempts.',
            ],
        ];

        $response = $this->handler->render(request(), $exception);

        $this->assertInstanceOf(CustomerErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors, JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    #[Test]
    public function it_returns_expected_response_if_resource_not_found_exception_is_thrown(): void
    {
        $exception = new ResourceNotFoundException('Payment with id 12345 could not be found');

        $expectedErrors = [
            '_metadata' => [
                'success' => false,
            ],
            'result' => [
                'message' => 'Payment with id 12345 could not be found',
            ],
        ];

        $response = $this->handler->render(request(), $exception);

        $this->assertInstanceOf(CustomerErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors, JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    #[Test]
    public function it_returns_expected_response_if_payment_validation_exception_is_thrown(): void
    {
        $exception = new PaymentValidationException(
            message: 'PaymentValidationException Message',
            errors: ['test error message']
        );

        $expectedErrors = [
            '_metadata' => [
                'success' => false,
            ],
            'result' => [
                'message' => 'PaymentValidationException Message',
                'errors' => [
                    [
                        'detail' => 'test error message',
                    ],
                ],
            ],
        ];

        $response = $this->handler->render(request(), $exception);

        $this->assertInstanceOf(CustomerErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors, JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    #[Test]
    public function it_returns_expected_response_if_unsupported_value_exception_is_thrown(): void
    {
        $exception = new UnsupportedValueException('UnsupportedValueException Message');

        $expectedErrors = [
            '_metadata' => [
                'success' => false,
            ],
            'result' => [
                'message' => 'UnsupportedValueException Message',
            ],
        ];

        $response = $this->handler->render(request(), $exception);

        $this->assertInstanceOf(CustomerErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors, JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    #[Test]
    public function it_returns_expected_response_if_method_not_allowed_exception_is_thrown(): void
    {
        $exception = new MethodNotAllowedHttpException(
            allow: ['POST'],
            message: 'The GET method is not supported for route api/v1/process-payments. Supported methods: POST.'
        );

        $expectedErrors = [
            '_metadata' => [
                'success' => false,
            ],
            'result' => [
                'message' => 'The GET method is not supported for route api/v1/process-payments. Supported methods: POST.',
            ],
        ];

        $response = $this->handler->render(request(), $exception);

        $this->assertInstanceOf(CustomerErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors, JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    #[Test]
    public function it_returns_expected_response_for_500_error(): void
    {
        JsonApi::config(['debug' => true]);

        $exception = new \Exception('Server Error', 500);
        $response = $this->handler->render(request(), $exception);

        $expectedErrors = [
            '_metadata' => [
                'success' => false,
            ],
            'result' => [
                'message' => 'Server Error',
            ],
        ];

        $this->assertInstanceOf(CustomerErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedErrors, JSON_THROW_ON_ERROR),
            $response->getContent()
        );
    }

    private function assertExceptionRenderedResponse(
        \Error|\Exception $exception,
        Response $response,
        bool $includeCode = false
    ): void {
        $this->assertInstanceOf(ErrorResponse::class, $response);
        $this->assertJsonStringEqualsJsonString(
            $this->getExpectedJsonFromException($exception, $includeCode),
            $response->getContent()
        );
    }

    private function getExpectedJsonFromException(\Error|\Exception $exception, bool $includeCode = false): string
    {
        $error = [
            'status' => (string)$exception->getCode(),
            'title' => HttpStatus::toMessage($exception->getCode()),
            'detail' => $exception->getMessage(),
        ];

        if ($includeCode) {
            $error['code'] = (string)$exception->getCode();
        }

        return json_encode(['errors' => [(object)$error]], JSON_THROW_ON_ERROR);
    }
}
