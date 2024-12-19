<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Gateways;

use App\Helpers\JsonDecoder;
use App\Helpers\SodiumEncryptHelper;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\Gateways\Worldpay;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Stubs\PaymentProcessor\WorldpayOperationStub;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\Unit\UnitTestCase;

class WorldpayTest extends UnitTestCase
{
    use MockeryPHPUnitIntegration;

    private Worldpay $gateway;
    /** @var MockObject&Client $guzzle */
    private Client $guzzle;

    /** @var MockInterface&LoggerInterface $logger */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guzzle = $this->createMock(originalClassName: Client::class);
        $this->logger = Mockery::spy(LoggerInterface::class);

        $this->gateway = Worldpay::make(credentials: WorldpayCredentialsStub::make());
        $this->gateway->setGuzzle($this->guzzle);
        $this->gateway->setLogger($this->logger);
    }

    #[Test]
    public function auth_capture_returns_false_if_guzzle_throws_exception_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->logger->allows('error')
            ->once()
            ->withArgs(static fn ($message) => str_starts_with($message, 'Networking Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertFalse(condition: $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture()));
    }

    #[Test]
    public function auth_capture_returns_false_if_guzzle_throws_exception_for_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            ClientException::create(
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 400)
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Client Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertFalse(
            $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture(isAchTransaction: true))
        );
    }

    #[Test]
    public function auth_capture_returns_true_if_worldpay_returns_success_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::authCaptureSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertTrue(condition: $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture()));
    }

    #[Test]
    #[DataProvider('authCaptureAchTransactionDataProvider')]
    public function auth_capture_returns_true_if_worldpay_returns_success_for_ach_transaction_with_token(
        array $inputData,
        string $expectedXmlRequest
    ): void {
        // TODO: think how to refactor \Tests\ProcessesDataProvidersClosures to allow such cases without str_replace for 3rd party classes (SodiumEncryptHelper) usage
        if (str_contains(haystack: $expectedXmlRequest, needle: '{{ACCOUNT_NUMBER}}')) {
            $expectedXmlRequest = str_replace(
                search: '{{ACCOUNT_NUMBER}}',
                replace: SodiumEncryptHelper::decrypt($inputData['ach_account_number']),
                subject: $expectedXmlRequest
            );
        }

        $this->guzzle->method('post')->willReturnCallback(static function ($url, $options) use (&$actualRequestBody) {
            $actualRequestBody = $options['body'];

            return new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::authCaptureSuccess(),
            );
        });

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertTrue(condition: $this->gateway->authCapture(inputData: $inputData));
        $this->assertXmlContains(expectedXml: $expectedXmlRequest, actualRequestBody: $actualRequestBody);
    }

    public static function authCaptureAchTransactionDataProvider(): iterable
    {
        $inputWithToken = WorldpayOperationStub::authCapture(isAchTransaction: true, withToken: true);

        yield 'with token' => [
            'inputData' => $inputWithToken,
            'expectedXmlRequest' => <<<XML
<PaymentAccount>
    <PaymentAccountID>{$inputWithToken['source']}</PaymentAccountID>
</PaymentAccount>
<DemandDepositAccount>
    <DDAAccountType>{$inputWithToken['ach_account_type']->ddaAccountTypeId()}</DDAAccountType>
</DemandDepositAccount>
XML
        ];

        $inputWithoutToken = WorldpayOperationStub::authCapture(isAchTransaction: true, withToken: false);

        yield 'with account and routing number' => [
            'inputData' => $inputWithoutToken,
            'expectedXmlRequest' => <<<XML
<DemandDepositAccount>
    <AccountNumber>{{ACCOUNT_NUMBER}}</AccountNumber>
    <RoutingNumber>{$inputWithoutToken['ach_routing_number']}</RoutingNumber>
    <DDAAccountType>{$inputWithoutToken['ach_account_type']->ddaAccountTypeId()}</DDAAccountType>
    <CheckType>{$inputWithoutToken['ach_account_type']->checkType()}</CheckType>
</DemandDepositAccount>
XML
        ];
    }

    #[Test]
    public function auth_capture_throws_exception_for_ach_transaction_with_missing_information(): void
    {
        $operationInputData = WorldpayOperationStub::authCapture(isAchTransaction: true, withToken: false);
        unset($operationInputData['ach_account_number']);

        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::authCaptureSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage(__('messages.payment_method.validate.incorrect_ach'));

        $this->gateway->authCapture(inputData: $operationInputData);
    }

    #[Test]
    public function cancel_returns_false_if_guzzle_throws_exception_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Networking Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertFalse(condition: $this->gateway->cancel(inputData: WorldpayOperationStub::cancel()));
    }

    #[Test]
    public function cancel_returns_false_if_guzzle_throws_exception_for_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            ServerException::create(
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 500)
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Server Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertFalse(
            $this->gateway->cancel(inputData: WorldpayOperationStub::cancel(isAchTransaction: true))
        );
    }

    #[Test]
    public function cancel_returns_true_if_worldpay_returns_success_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::cancelSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertTrue(condition: $this->gateway->cancel(inputData: WorldpayOperationStub::cancel()));
    }

    #[Test]
    public function cancel_returns_true_if_worldpay_returns_success_for_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::cancelSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertTrue(condition: $this->gateway->cancel(inputData: WorldpayOperationStub::cancel(isAchTransaction: true)));
    }

    #[Test]
    public function credit_returns_false_if_guzzle_throws_exception_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new BadResponseException(
                message: 'Connection Error',
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 500)
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Connection Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertFalse(condition: $this->gateway->credit(inputData: WorldpayOperationStub::credit()));
    }

    #[Test]
    public function credit_returns_false_if_guzzle_throws_exception_for_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            ClientException::create(
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 400)
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Client Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertFalse(
            $this->gateway->credit(inputData: WorldpayOperationStub::credit(isAchTransaction: true))
        );
    }

    #[Test]
    public function credit_returns_true_if_worldpay_returns_success_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::creditSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertTrue(condition: $this->gateway->credit(inputData: WorldpayOperationStub::credit()));
    }

    #[Test]
    public function credit_returns_true_if_worldpay_returns_success_for_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::creditSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertTrue(condition: $this->gateway->credit(inputData: WorldpayOperationStub::credit(isAchTransaction: true)));
    }

    #[Test]
    public function status_returns_false_if_guzzle_throws_exception_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Networking Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertFalse(condition: $this->gateway->status(inputData: WorldpayOperationStub::status()));
    }

    #[Test]
    public function status_returns_false_if_guzzle_throws_exception_for_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            ClientException::create(
                request: new Request('post', 'worlpayuri.com'),
                response: new Response(status: 400)
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Client Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertFalse(
            $this->gateway->status(inputData: WorldpayOperationStub::status(isAchTransaction: true))
        );
    }

    #[Test]
    public function status_returns_true_if_worldpay_returns_success_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::statusSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertTrue(condition: $this->gateway->status(inputData: WorldpayOperationStub::status()));
    }

    #[Test]
    public function status_returns_true_if_worldpay_returns_success_for_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::statusSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->assertTrue(condition: $this->gateway->status(inputData: WorldpayOperationStub::status(isAchTransaction: true)));
    }

    #[Test]
    #[DataProvider('worldpayUnsuccessProvider')]
    public function update_payment_account_returns_false_if_guzzle_throws_exception(PaymentTypeEnum $paymentType): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Networking Error'));

        $this->gateway->setPaymentType(paymentType: $paymentType);

        $this->assertFalse(
            condition: $this->gateway->updatePaymentAccount(
                paymentAccountId: Str::uuid()->toString(),
                paymentMethod: PaymentMethod::factory()->withoutRelationships()->make(attributes: [
                    'payment_type_id' => $paymentType->value,
                    'id' => random_int(100, 1000),
                ])
            )
        );
    }

    public static function worldpayUnsuccessProvider(): \Iterator
    {
        yield 'non ach' => [
            'paymentType' => PaymentTypeEnum::CC,
        ];
        yield 'ach' => [
            'paymentType' => PaymentTypeEnum::ACH,
        ];
    }

    #[Test]
    #[DataProvider('worldpaySuccessProvider')]
    public function update_payment_account_returns_true_if_worldpay_returns_success(PaymentTypeEnum $paymentType): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::updatePaymentAccountSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: $paymentType);

        $this->assertTrue(condition: $this->gateway->updatePaymentAccount(
            paymentAccountId: Str::uuid()->toString(),
            paymentMethod: PaymentMethod::factory()->withoutRelationships()->make(attributes: [
                'payment_type_id' => $paymentType->value,
                'id' => random_int(100, 1000),
            ])
        ));
    }

    public static function worldpaySuccessProvider(): \Iterator
    {
        yield 'non ach' => [
            'paymentType' => PaymentTypeEnum::CC,
        ];
        yield 'ach' => [
            'paymentType' => PaymentTypeEnum::ACH,
        ];
    }

    #[Test]
    public function authorize_returns_false_if_guzzle_throws_exception_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Networking Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertFalse(condition: $this->gateway->authorize(inputData: WorldpayOperationStub::authorize()));
    }

    #[Test]
    public function authorize_throws_exception_for_ach_transaction(): void
    {
        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage(__('messages.operation.authorization.ach_not_supported'));
        $this->gateway->authorize(inputData: WorldpayOperationStub::authorize(isAchTransaction: true));
    }

    #[Test]
    public function authorize_returns_true_if_worldpay_returns_success_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::authorizeSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertTrue(condition: $this->gateway->authorize(inputData: WorldpayOperationStub::authorize()));
    }

    #[Test]
    public function capture_returns_false_if_guzzle_throws_exception_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->logger->allows('error')
            ->withArgs(static fn ($message) => str_starts_with($message, 'Networking Error'));

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertFalse(condition: $this->gateway->capture(inputData: WorldpayOperationStub::capture()));
    }

    #[Test]
    public function capture_throws_exception_for_ach_transaction(): void
    {
        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::ACH);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage(__('messages.operation.capture.ach_not_supported'));
        $this->gateway->capture(inputData: WorldpayOperationStub::capture(isAchTransaction: true));
    }

    #[Test]
    public function capture_returns_true_if_worldpay_returns_success_for_non_ach_transaction(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::captureSuccess(),
            )
        );

        $this->gateway->setPaymentType(paymentType: PaymentTypeEnum::CC);

        $this->assertTrue(condition: $this->gateway->capture(inputData: WorldpayOperationStub::capture()));
    }

    #[Test]
    public function get_methods_without_doing_anything_because_it_simply_return_properties(): void
    {
        $methods = [
            'setRequest' => 'getRequest',
            'setTransactionResponseId' => 'getTransactionId',
            'setTransactionResponseCode' => 'getTransactionResponseCode',
        ];

        foreach ($methods as $setMethod => $getMethod) {
            $this->gateway->$setMethod('');
            $this->gateway->$getMethod();
        }
        $this->assertTrue(true);
    }

    #[Test]
    #[DataProvider('isSuccessfulProvider')]
    public function is_successful_should_return_correct_data_with_transaction_response_code(array $input, bool $expected): void
    {
        $this->gateway->setTransactionResponseCode($input['transactionResponseCode']);

        $actualResult = $this->gateway->isSuccessful();

        $this->assertEquals($expected, $actualResult);
    }

    public static function isSuccessfulProvider(): \Iterator
    {
        yield 'successful' => [
            'input' => [
                'transactionResponseCode' => '0',
            ],
            'expected' => true,
        ];
        yield 'unsuccessful' => [
            'input' => [
                'transactionResponseCode' => '1',
            ],
            'expected' => false,
        ];
    }

    #[Test]
    #[DataProvider('getTransactionStatusProvider')]
    public function get_transaction_status_should_return_correct_data_with_transaction_response_code(array $input, string $expected): void
    {
        $this->gateway->setTransactionResponseCode($input['transactionResponseCode']);

        $actualResult = $this->gateway->getTransactionStatus();

        $this->assertEquals($expected, $actualResult);
    }

    public static function getTransactionStatusProvider(): \Iterator
    {
        yield 'successful' => [
            'input' => [
                'transactionResponseCode' => '0',
            ],
            'expected' => '0',
        ];
        yield 'unsuccessful' => [
            'input' => [
                'transactionResponseCode' => '5',
            ],
            'expected' => '5',
        ];
    }

    #[Test]
    #[DataProvider('isSuccessProvider')]
    public function get_http_code_returns_correct_data_with_given_code(int $statusCode, int $expected): void
    {
        $this->gateway->setHttpCode($statusCode);
        $this->assertSame($this->gateway->getHttpCode(), $expected);
    }

    public static function isSuccessProvider(): \Iterator
    {
        yield '200 code' => [
            'statusCode' => 200,
            'expected' => 200,
        ];
        yield '222 code' => [
            'statusCode' => 222,
            'expected' => 222,
        ];
    }

    #[Test]
    public function get_error_message_returns_correct_data_with_given_data(): void
    {
        $this->gateway->setErrorMessage('Some Message');
        $this->assertSame($this->gateway->getErrorMessage(), 'Some Message');
    }

    #[Test]
    public function get_response_returns_correct_data_with_given_data(): void
    {
        $this->gateway->addResponse('Some Message');
        $this->assertSame($this->gateway->getResponse(), json_encode([null, 'Some Message']));
    }

    #[Test]
    public function get_payment_account_returns_response_object_from_worldpay(): void
    {
        $paymentAccountQueryResponse = WorldpayResponseStub::paymentAccountQuerySuccess();

        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $paymentAccountQueryResponse,
            )
        );

        $result = $this->gateway->getPaymentAccount(
            paymentAccountId: Str::uuid()->toString(),
            paymentAccountReferenceNumber: null
        );
        $parsedExpectedResponse = JsonDecoder::decode(JsonDecoder::encode(simplexml_load_string($paymentAccountQueryResponse)));

        $this->assertEquals((object)array_pop($parsedExpectedResponse['Response']['QueryData']['Items']), $result);
    }

    #[Test]
    public function get_payment_account_returns_null_if_guzzle_throws_exception(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->assertNull($this->gateway->getPaymentAccount(
            paymentAccountId: '',
            paymentAccountReferenceNumber: 'SampleReferenceNumber'
        ));
    }

    #[Test]
    public function get_payment_account_returns_null_if_empty_input(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->assertNull($this->gateway->getPaymentAccount());
    }

    #[Test]
    public function get_payment_account_returns_null_if_payment_account_not_found_in_worldpay(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::paymentAccountQueryNotFound(),
            )
        );

        $this->assertNull($this->gateway->getPaymentAccount(paymentAccountId: Str::uuid()->toString()));
    }

    #[Test]
    public function create_transaction_setup_returns_null_if_guzzle_throws_exception(): void
    {
        $this->guzzle->method('post')->willThrowException(
            new ConnectException(
                message: 'something wrong with connection',
                request: new Request('post', 'worlpayuri.com')
            )
        );

        $this->assertNull($this->gateway->createTransactionSetup(
            referenceId: 1234,
            callbackUrl: 'https://'
        ));
    }

    #[Test]
    public function create_transaction_setup_returns_null_when_worldpay_failed(): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::createTransactionSetupUnsuccess(),
            )
        );

        $this->assertNull($this->gateway->createTransactionSetup(
            referenceId: 1234,
            callbackUrl: 'https://'
        ));
    }

    #[Test]
    public function create_transaction_setup_returns_response_object_from_worldpay(): void
    {
        $response = WorldpayResponseStub::createTransactionSetupSuccess();

        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $response,
            )
        );

        $result = $this->gateway->createTransactionSetup(
            referenceId: 1234,
            callbackUrl: 'https://'
        );
        $parsedExpectedResponse = JsonDecoder::decode(JsonDecoder::encode(simplexml_load_string($response)));

        $this->assertEquals($parsedExpectedResponse['Response']['TransactionSetup'], $result);
    }

    #[Test]
    public function generate_transaction_setup_url_returns_string_as_env(): void
    {
        $this->app['env'] = 'local';
        $res = $this->gateway->generateTransactionSetupUrl(transactionSetupId: 123456789);

        $this->assertSame('https://certtransaction.hostedpayments.com?TransactionSetupID=123456789', $res);

        $this->app['env'] = 'production';
        $res = $this->gateway->generateTransactionSetupUrl(transactionSetupId: 123456789);

        $this->assertSame('https://transaction.hostedpayments.com?TransactionSetupID=123456789', $res);
    }

    #[Test]
    #[DataProvider('declineReasonMappingProvider')]
    public function it_retrieves_decline_reason_as_expected(int $errorCode, DeclineReasonEnum $expectedDeclinedReason): void
    {
        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::authCaptureUnsuccess(
                    errorMessage: __('messages.operation.something_went_wrong'),
                    errorCode: (string)$errorCode
                ),
            )
        );

        $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture());

        $this->assertSame(expected: $expectedDeclinedReason, actual: $this->gateway->getDeclineReason());
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

    #[Test]
    public function it_adds_warning_when_get_non_mapped_response_code_and_set_decline_reason_as_declined(): void
    {
        $nonExistingCode = 12345678;

        $this->guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::authCaptureUnsuccess(
                    errorMessage: __('messages.operation.something_went_wrong'),
                    errorCode: (string)$nonExistingCode
                ),
            )
        );

        $this->logger->allows('warning')
            ->once()
            ->withArgs(static fn ($message) => str_starts_with($message, __('messages.gateway.found_unmapped_decline_reason')));

        $this->gateway->authCapture(inputData: WorldpayOperationStub::authCapture());

        $this->assertSame(expected: DeclineReasonEnum::DECLINED, actual: $this->gateway->getDeclineReason());
    }

    private function assertXmlContains(string $expectedXml, string $actualRequestBody): void
    {
        $this->assertStringContainsStringIgnoringLineEndings(
            needle: str_replace([' ', PHP_EOL], '', $expectedXml),
            haystack: $actualRequestBody
        );
    }

    protected function tearDown(): void
    {
        unset($this->guzzle, $this->logger, $this->gateway);

        parent::tearDown();
    }
}
