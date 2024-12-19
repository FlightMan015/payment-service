<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Gateways;

use App\Api\DTO\GatewayInitializationDTO;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Gateways\WorldpayTokenexTransparent;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Log\Logger;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Stubs\PaymentProcessor\WorldpayOperationStub;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\Unit\UnitTestCase;

class WorldpayTokenexTransparentTest extends UnitTestCase
{
    private WorldpayTokenexTransparent $gateway;

    #[Test]
    #[DataProvider('guzzleExceptionProvider')]
    public function authorize_returns_false_when_guzzle_throws_error(bool $isAch, \Throwable $exception): void
    {
        $guzzle = $this->mockGuzzleClientPost(exception: $exception);
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        if ($isAch) {
            $this->expectException(InvalidOperationException::class);
            $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.ach_not_supported'));
        }

        $res = $this->gateway->authorize(inputData: WorldpayOperationStub::authorize(isAchTransaction: $isAch));

        if (!$isAch) {
            $this->assertFalse($res);
            $this->assertSame($exception->getMessage(), $this->gateway->getErrorMessage());
        }
    }

    #[Test]
    #[DataProvider('guzzleExceptionProvider')]
    public function auth_capture_returns_false_when_guzzle_throws_error(bool $isAch, \Throwable $exception): void
    {
        $guzzle = $this->mockGuzzleClientPost(exception: $exception);
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        if ($isAch) {
            $this->expectException(InvalidOperationException::class);
            $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.ach_not_supported'));
        }

        $res = $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture(isAchTransaction: $isAch));

        if (!$isAch) {
            $this->assertFalse($res);
            $this->assertSame($exception->getMessage(), $this->gateway->getErrorMessage());
        }
    }

    #[Test]
    public function auth_capture_throws_exception_for_cc_payment_method_without_expiration_data(): void
    {
        $guzzle = $this->mockGuzzleClientPost();

        $paymentMethod = PaymentMethod::factory()->cc()->makeWithRelationships(
            attributes: ['cc_expiration_month' => null, 'cc_expiration_year' => null],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );
        $this->initGateway(paymentMethod: $paymentMethod, guzzleClient: $guzzle);

        $this->expectException(CreditCardValidationException::class);
        $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required'));

        $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture());
    }

    #[Test]
    #[DataProvider('guzzleExceptionProvider')]
    public function capture_returns_false_when_guzzle_throws_error(bool $isAch, \Throwable $exception): void
    {
        $guzzle = $this->mockGuzzleClientPost(exception: $exception);
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        if ($isAch) {
            $this->expectException(InvalidOperationException::class);
            $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.ach_not_supported'));
        }

        $res = $this->gateway->capture(inputData: WorldpayOperationStub::capture(isAchTransaction: $isAch));

        if (!$isAch) {
            $this->assertFalse($res);
            $this->assertSame($exception->getMessage(), $this->gateway->getErrorMessage());
        }
    }

    #[Test]
    public function capture_throws_exception_for_cc_payment_method_without_expiration_data(): void
    {
        $guzzle = $this->mockGuzzleClientPost();

        $paymentMethod = PaymentMethod::factory()->cc()->makeWithRelationships(
            attributes: ['cc_expiration_month' => null, 'cc_expiration_year' => null],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );
        $this->initGateway(paymentMethod: $paymentMethod, guzzleClient: $guzzle);

        $this->expectException(CreditCardValidationException::class);
        $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required'));

        $this->gateway->capture(inputData: WorldpayOperationStub::capture());
    }

    #[Test]
    #[DataProvider('guzzleExceptionProvider')]
    public function cancel_returns_false_when_guzzle_throws_error(bool $isAch, \Throwable $exception): void
    {
        $guzzle = $this->mockGuzzleClientPost(exception: $exception);
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        if ($isAch) {
            $this->expectException(InvalidOperationException::class);
            $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.ach_not_supported'));
        }

        $res = $this->gateway->cancel(inputData: WorldpayOperationStub::cancel(isAchTransaction: $isAch));

        if (!$isAch) {
            $this->assertFalse($res);
            $this->assertSame($exception->getMessage(), $this->gateway->getErrorMessage());
        }
    }

    #[Test]
    #[DataProvider('guzzleExceptionProvider')]
    public function credit_returns_false_when_guzzle_throws_error(bool $isAch, \Throwable $exception): void
    {
        $guzzle = $this->mockGuzzleClientPost(exception: $exception);
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        if ($isAch) {
            $this->expectException(InvalidOperationException::class);
            $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.ach_not_supported'));
        }

        $res = $this->gateway->credit(inputData: WorldpayOperationStub::credit(isAchTransaction: $isAch));

        if (!$isAch) {
            $this->assertFalse($res);
            $this->assertSame($exception->getMessage(), $this->gateway->getErrorMessage());
        }
    }

    #[Test]
    #[DataProvider('guzzleExceptionProvider')]
    public function status_returns_false_when_guzzle_throws_error(bool $isAch, \Throwable $exception): void
    {
        $guzzle = $this->mockGuzzleClientPost(exception: $exception);
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        if ($isAch) {
            $this->expectException(InvalidOperationException::class);
            $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.ach_not_supported'));
        }

        $res = $this->gateway->status(inputData: WorldpayOperationStub::status(isAchTransaction: $isAch));

        if (!$isAch) {
            $this->assertFalse($res);
            $this->assertSame($exception->getMessage(), $this->gateway->getErrorMessage());
        }
    }

    #[Test]
    public function status_throws_exception_for_cc_payment_method_without_expiration_data(): void
    {
        $guzzle = $this->mockGuzzleClientPost();

        $paymentMethod = PaymentMethod::factory()->cc()->makeWithRelationships(
            attributes: ['cc_expiration_month' => null, 'cc_expiration_year' => null],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );
        $this->initGateway(paymentMethod: $paymentMethod, guzzleClient: $guzzle);

        $this->expectException(CreditCardValidationException::class);
        $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required'));

        $this->gateway->status(inputData: WorldpayOperationStub::status());
    }

    #[Test]
    #[DataProvider('tokenexReturnsErrorProvider')]
    public function it_returns_true_and_parse_to_worldpay_response_format_when_error_from_tokenex(
        bool $isAch,
        string $method,
        string $errorMessage,
    ): void {
        $guzzle = $this->mockGuzzleClientPost(
            responseBody: json_encode([
                'referenceNumber' => '23102405005976641537',
                'success' => false,
                'error' => $errorMessage,
                'message' => '',
            ], JSON_THROW_ON_ERROR)
        );
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        $res = $this->gateway->$method(inputData: WorldpayOperationStub::$method(isAchTransaction: $isAch));

        $this->assertTrue($res);
        $this->assertSame(__('messages.worldpay_tokenex_transparent.error', ['message' => $errorMessage]), $this->gateway->getErrorMessage());
    }

    #[Test]
    #[DataProvider('callingWorldpaySuccessfullyProvider')]
    public function it_returns_true_and_parsed_response_from_worldpay_after_calling_worldpay_successfully(
        string $method,
        bool $isAch,
        string $responseBody,
        string|null $expectedErrorMessage = null
    ): void {
        $guzzle = $this->mockGuzzleClientPost(responseBody: $responseBody);
        $this->initGateway(guzzleClient: $guzzle, isAch: $isAch);

        $res = $this->gateway->$method(inputData: WorldpayOperationStub::$method(isAchTransaction: $isAch));

        $this->assertTrue($res);
        $this->assertSame(json_decode(json_encode(simplexml_load_string($responseBody)), true), $this->gateway->getParsedResponse());

        if (!is_null($expectedErrorMessage)) {
            $this->assertSame($expectedErrorMessage, $this->gateway->getErrorMessage());
        }
    }

    #[Test]
    public function get_payment_account_throws_exception(): void
    {
        $guzzle = $this->mockGuzzleClientPost();
        $this->initGateway(guzzleClient: $guzzle);

        $this->expectException(InvalidOperationException::class);

        $this->gateway->getPaymentAccount();
    }

    #[Test]
    #[DataProvider('declineReasonMappingProvider')]
    public function it_retrieves_decline_reason_as_expected(int $errorCode, DeclineReasonEnum $expectedDeclinedReason): void
    {
        $guzzle = $this->mockGuzzleClientPost(
            responseBody: WorldpayResponseStub::authCaptureUnsuccess(errorMessage: __('messages.operation.something_went_wrong'), errorCode: (string)$errorCode)
        );

        $this->initGateway(guzzleClient: $guzzle);

        $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture());

        $this->assertSame(expected: $expectedDeclinedReason, actual: $this->gateway->getDeclineReason());
    }

    #[Test]
    public function it_adds_warning_when_get_non_mapped_response_code_and_set_decline_reason_as_declined(): void
    {
        $nonExistingCode = 12345678;

        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::spy($this->createMock(originalClassName: Logger::class));

        $guzzle = $this->mockGuzzleClientPost(
            responseBody: WorldpayResponseStub::authCaptureUnsuccess(errorMessage: __('messages.operation.something_went_wrong'), errorCode: (string)$nonExistingCode)
        );

        $this->initGateway(
            guzzleClient: $guzzle,
            logger: $logger
        );

        $logger->allows('warning')
            ->once()
            ->withArgs(static fn ($message) => str_starts_with($message, __('messages.gateway.found_unmapped_decline_reason')));

        $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture());

        $this->assertSame(expected: DeclineReasonEnum::DECLINED, actual: $this->gateway->getDeclineReason());
    }

    public static function guzzleExceptionProvider(): \Iterator
    {
        yield 'ConnectException' => [
            'isAch' => false,
            'exception' => new ConnectException(message: 'Test exception', request: new Request('post', 'worlpayuri.com')),
        ];
        yield 'ConnectException-ACH' => [
            'isAch' => true,
            'exception' => new ConnectException(message: 'Test exception', request: new Request('post', 'worlpayuri.com')),
        ];
        yield 'ClientException' => [
            'isAch' => false,
            'exception' => new ClientException(
                message: 'Test exception ClientException',
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 400)
            ),
        ];
        yield 'ClientException-ACH' => [
            'isAch' => true,
            'exception' => new ClientException(
                message: 'Test exception ClientException',
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 400)
            ),
        ];
        yield 'ServerException' => [
            'isAch' => false,
            'exception' => new ServerException(
                message: 'Test exception ServerException',
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 500)
            ),
        ];
        yield 'ServerException-ACH' => [
            'isAch' => true,
            'exception' => new ServerException(
                message: 'Test exception ServerException',
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 500)
            ),
        ];
        yield 'GuzzleException-ACH' => [
            'isAch' => true,
            'exception' => new BadResponseException(
                message: 'Test exception GuzzleException',
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 422)
            ),
        ];
        yield 'GuzzleException' => [
            'isAch' => false,
            'exception' => new BadResponseException(
                message: 'Test exception GuzzleException',
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 422)
            ),
        ];
    }

    public static function tokenexReturnsErrorProvider(): \Iterator
    {
        yield 'authorize non-ACH' => [
            'isAch' => false,
            'method' => 'authorize',
            'errorMessage' => '8000 : TokenRequestorId not found',
        ];
        yield 'authCapture non-ACH' => [
            'isAch' => false,
            'method' => 'authCapture',
            'errorMessage' => '8000 : TokenRequestorId not found',
        ];
        yield 'cancel non-ACH' => [
            'isAch' => false,
            'method' => 'cancel',
            'errorMessage' => '8000 : TokenRequestorId not found',
        ];
        yield 'credit non-ACH' => [
            'isAch' => false,
            'method' => 'credit',
            'errorMessage' => '8000 : TokenRequestorId not found',
        ];
        yield 'status non-ACH' => [
            'isAch' => false,
            'method' => 'status',
            'errorMessage' => '8000 : TokenRequestorId not found',
        ];
    }

    public static function getPaymentAccountProcessUnsuccessfulProvider(): \Iterator
    {
        yield 'throw error' => [
            'guzzle' => [
                'exception' => new ConnectException(message: 'Test exception', request: new Request('post', 'worlpayuri.com')),
                'responseBody' => null,
            ],
        ];
        yield 'payment account is not found' => [
            'guzzle' => [
                'exception' => null,
                'responseBody' => WorldpayResponseStub::paymentAccountQueryNotFound(),
            ],
        ];
    }

    public static function callingWorldpaySuccessfullyProvider(): \Iterator
    {
        yield 'authorize - successfully' => [
            'method' => 'authorize',
            'isAch' => false,
            'responseBody' => WorldpayResponseStub::authorizeSuccess(),
        ];
        yield 'authorize - unsuccessfully' => [
            'method' => 'authorize',
            'isAch' => false,
            'responseBody' => WorldpayResponseStub::authorizeUnsuccessful(),
            'expectedErrorMessage' => 'PAYMENT ACCOUNT NOT FOUND',
        ];
        yield 'capture - successfully' => [
            'method' => 'capture',
            'isAch' => false,
            'responseBody' => WorldpayResponseStub::captureSuccess(),
        ];
        yield 'authCapture - successfully' => [
            'method' => 'authCapture',
            'isAch' => false,
            'responseBody' => WorldpayResponseStub::authCaptureSuccess(),
        ];
        yield 'authCapture - unsuccessfully' => [
            'method' => 'authCapture',
            'isAch' => false,
            'responseBody' => WorldpayResponseStub::authCaptureUnsuccess(errorMessage: 'Error message here'),
            'expectedErrorMessage' => 'Error message here',
        ];
    }

    public static function declineReasonMappingProvider(): iterable
    {
        yield 'declined => declined' => [
            'errorCode' => WorldpayResponseCodeEnum::DECLINED->value,
            'expectedDeclinedReason' => DeclineReasonEnum::DECLINED,
        ];

        yield 'expired card => expired' => [
            'errorCode' => WorldpayResponseCodeEnum::EXPIRED_CARD->value,
            'expectedDeclinedReason' => DeclineReasonEnum::EXPIRED,
        ];

        yield 'duplicate => duplicate' => [
            'errorCode' => WorldpayResponseCodeEnum::DUPLICATE->value,
            'expectedDeclinedReason' => DeclineReasonEnum::DUPLICATE,
        ];

        yield 'non financial card => invalid' => [
            'errorCode' => WorldpayResponseCodeEnum::NON_FINANCIAL_CARD->value,
            'expectedDeclinedReason' => DeclineReasonEnum::INVALID,
        ];

        yield 'pick up card => fraud' => [
            'errorCode' => WorldpayResponseCodeEnum::PICK_UP_CARD->value,
            'expectedDeclinedReason' => DeclineReasonEnum::FRAUD,
        ];

        yield 'referral call issuer => contact financial institution' => [
            'errorCode' => WorldpayResponseCodeEnum::REFERRAL_CALL_ISSUER->value,
            'expectedDeclinedReason' => DeclineReasonEnum::CONTACT_FINANCIAL_INSTITUTION,
        ];

        yield 'balance not available => insufficient funds' => [
            'errorCode' => WorldpayResponseCodeEnum::BALANCE_NOT_AVAILABLE->value,
            'expectedDeclinedReason' => DeclineReasonEnum::INSUFFICIENT_FUNDS,
        ];

        yield 'not defined => invalid' => [
            'errorCode' => WorldpayResponseCodeEnum::NOT_DEFINED->value,
            'expectedDeclinedReason' => DeclineReasonEnum::INVALID,
        ];

        yield 'invalid data => invalid' => [
            'errorCode' => WorldpayResponseCodeEnum::INVALID_DATA->value,
            'expectedDeclinedReason' => DeclineReasonEnum::INVALID,
        ];

        yield 'invalid account => invalid' => [
            'errorCode' => WorldpayResponseCodeEnum::INVALID_ACCOUNT->value,
            'expectedDeclinedReason' => DeclineReasonEnum::INVALID,
        ];

        yield 'invalid request => invalid' => [
            'errorCode' => WorldpayResponseCodeEnum::INVALID_REQUEST->value,
            'expectedDeclinedReason' => DeclineReasonEnum::INVALID,
        ];

        yield 'authorization failed => declined' => [
            'errorCode' => WorldpayResponseCodeEnum::AUTHORIZATION_FAILED->value,
            'expectedDeclinedReason' => DeclineReasonEnum::DECLINED,
        ];

        yield 'not authorized => declined' => [
            'errorCode' => WorldpayResponseCodeEnum::NOT_AUTHORIZED->value,
            'expectedDeclinedReason' => DeclineReasonEnum::DECLINED,
        ];

        yield 'out of balance => insufficient funds' => [
            'errorCode' => WorldpayResponseCodeEnum::OUT_OF_BALANCE->value,
            'expectedDeclinedReason' => DeclineReasonEnum::INSUFFICIENT_FUNDS,
        ];

        yield 'communication error => error' => [
            'errorCode' => WorldpayResponseCodeEnum::COMMUNICATION_ERROR->value,
            'expectedDeclinedReason' => DeclineReasonEnum::ERROR,
        ];

        yield 'host error => error' => [
            'errorCode' => WorldpayResponseCodeEnum::HOST_ERROR->value,
            'expectedDeclinedReason' => DeclineReasonEnum::ERROR,
        ];

        yield 'error => error' => [
            'errorCode' => WorldpayResponseCodeEnum::ERROR->value,
            'expectedDeclinedReason' => DeclineReasonEnum::ERROR,
        ];

        yield 'default => declined' => [
            'errorCode' => 12345678,
            'expectedDeclinedReason' => DeclineReasonEnum::DECLINED,
        ];
    }

    private function mockGuzzleClientPost(
        string|null $responseBody = null,
        \Throwable|null $exception = null
    ): Client|MockObject {
        /**
         * @var Client|MockObject
         */
        $guzzle = $this->createMock(originalClassName: Client::class);

        if (!is_null($responseBody)) {
            $guzzle->method('post')->willReturn(
                new Response(
                    status: 200,
                    headers: ['Content-Type' => 'application/json'],
                    body: $responseBody,
                )
            );
        } elseif (!is_null($exception)) {
            $guzzle->method('post')->willThrowException($exception);
        }

        return $guzzle;
    }

    private function initGateway(
        PaymentMethod|null $paymentMethod = null,
        Client|MockObject|null $guzzleClient = null,
        LoggerInterface|null $logger = null,
        bool $isAch = false
    ): void {
        if (is_null($guzzleClient)) {
            $guzzleClient = $this->createMock(originalClassName: Client::class);
        }

        if (is_null($logger)) {
            /**
             * @var LoggerInterface $logger
             */
            $logger = Mockery::spy($this->createMock(originalClassName: Logger::class));
        }

        if (is_null($paymentMethod)) {
            if ($isAch) {
                $paymentMethod = PaymentMethod::factory()
                    ->ach()
                    ->makeWithRelationships(relationships: [
                        'account' => Account::factory()->withoutRelationships()->make()
                    ]);
            } else {
                $paymentMethod = PaymentMethod::factory()->cc()->makeWithRelationships(relationships: ['account' => Account::factory()->withoutRelationships()->make()]);
            }
        }

        $this->gateway = WorldpayTokenexTransparent::make(
            gatewayInitializationDTO: new GatewayInitializationDTO(
                gatewayId: $paymentMethod->payment_gateway_id,
                officeId: $paymentMethod->account->area_id,
                creditCardToken: $paymentMethod->cc_token,
                creditCardExpirationMonth: is_null($paymentMethod->cc_expiration_month) ? null : (int)$paymentMethod->cc_expiration_month,
                creditCardExpirationYear: is_null($paymentMethod->cc_expiration_year) ? null : (int)$paymentMethod->cc_expiration_year,
            ),
            credentials: WorldpayCredentialsStub::make(),
        );

        $this->gateway->setGuzzle($guzzleClient);
        $this->gateway->setLogger($logger);
        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::from($paymentMethod->payment_type_id));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->gateway);
    }
}
