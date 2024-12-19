<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations;

use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Helpers\JsonDecoder;
use App\Models\Payment;
use App\Models\Transaction;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\Gateways\NullGateway;
use App\PaymentProcessor\Operations\Capture;
use App\PaymentProcessor\Operations\Validators\CaptureValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\GatewayMockingTrait;
use Tests\Helpers\Traits\ValidatorMockingTrait;
use Tests\Stubs\PaymentProcessor\OperationStub;
use Tests\Unit\UnitTestCase;

class CaptureTest extends UnitTestCase
{
    use GatewayMockingTrait;
    use ValidatorMockingTrait;

    private Capture $operation;

    protected function setUp(): void
    {
        parent::setUp();

        $payment = Payment::factory()->withoutRelationships()->make();

        Payment::creating(static fn () => false);
        Transaction::creating(static fn () => false);

        $this->operation = OperationStub::capture();
        $this->operation->setReferenceId(referenceId: $payment->id);
    }

    #[Test]
    public function process_method_sets_raw_request_and_calls_gateway_capture_method(): void
    {
        $request = [
            'transaction_id' => $this->operation->getReferenceTransactionId(),
            'amount' => $this->operation->getAmount()->getAmount(),
            'currency' => $this->operation->getAmount()->getCurrency(),
            'reference_id' => $this->operation->getReferenceId()
        ];

        $gateway = $this->mockGateway();
        $gateway->expects($this->once())->method('capture')->with($request);

        $this->operation->setGateway(gateway: $gateway);
        $this->operation->process();

        $this->assertSame(JsonDecoder::encode($request), $this->operation->getRawRequest());
    }

    #[Test]
    public function process_method_sets_error_message_if_gateway_throws_exception(): void
    {
        $exceptionMessage = 'Test Error Message';

        $this->operation->setGateway(gateway: $this->mockGatewayWillThrowException(
            method: 'capture',
            exceptionMessage: $exceptionMessage
        ));

        $this->operation->process();

        $this->assertFalse(condition: $this->operation->isSuccessful());
        $this->assertSame(expected: $exceptionMessage, actual: $this->operation->getErrorMessage());
    }

    #[Test]
    public function process_method_throws_unprocessable_exception_when_operating_ach(): void
    {
        $exceptionMessage = 'Test Error Message';
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->operation->setGateway(gateway: $this->mockGatewayWillThrowException(
            method: 'capture',
            exceptionClass: InvalidOperationException::class,
            exceptionMessage: $exceptionMessage
        ));

        $this->operation->process();
    }

    #[Test]
    public function handle_response_method_sets_response_message_transaction_id_transaction_status_and_is_successful(): void
    {
        $response = [
            'response' => 'some_response',
            'message' => null,
            'transaction_id' => 'ABC1234',
            'transaction_status' => 'COMPLETED'
        ];

        $gateway = $this->mockGateway(
            response: $response['response'],
            errorMessage: $response['message'],
            transactionId: $response['transaction_id'],
            transactionStatus: $response['transaction_status']
        );
        $this->operation->setGateway(gateway: $gateway);

        $transactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $transactionRepository->expects($this->once())
            ->method('create');
        app()->instance(PaymentTransactionRepository::class, $transactionRepository);

        $this->operation->handleResponse();

        $this->assertEquals(expected: $response['response'], actual: $this->operation->getRawResponse());
        $this->assertEquals(expected: $response['message'], actual: $this->operation->getErrorMessage());
        $this->assertEquals(expected: $response['transaction_id'], actual: $this->operation->getTransactionId());
        $this->assertEquals(expected: $response['transaction_status'], actual: $this->operation->getTransactionStatus());
        $this->assertTrue($this->operation->isSuccessful());
    }

    #[Test]
    public function validate_method_returns_capture_instance_if_validator_returns_true(): void
    {
        $this->operation->setValidator(validator: $this->mockSuccessValidator(className: CaptureValidator::class));

        $this->assertInstanceOf(expected: Capture::class, actual: $this->operation->validate());
        $this->assertTrue($this->operation->validate()->isSuccessful());
    }

    #[Test]
    public function validate_method_throws_operation_validation_exception_if_validator_returns_false(): void
    {
        $this->operation->setValidator(validator: $this->mockFailValidator(className: CaptureValidator::class));

        $this->expectException(OperationValidationException::class);

        $this->operation->validate();
    }

    #[Test]
    public function process_method_throws_invalid_operation_exception_when_gateway_throws_invalid_operation_exception(): void
    {
        $exception = new InvalidOperationException(message: 'Some error message here');
        /** @var MockObject|NullGateway $gateway */
        $gateway = $this->getMockBuilder(NullGateway::class)->getMock();
        $gateway->method('capture')->willThrowException($exception);
        $this->operation->setGateway(gateway: $gateway);

        $this->expectExceptionObject($exception);

        $this->operation->process();
    }

    #[Test]
    public function process_method_throws_credit_card_validation_exception_when_gateway_throws_credit_card_validation_exception(): void
    {
        $exception = new CreditCardValidationException(message: __('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required'));
        /** @var MockObject|NullGateway $gateway */
        $gateway = $this->getMockBuilder(NullGateway::class)->getMock();
        $gateway->method('capture')->willThrowException($exception);
        $this->operation->setGateway(gateway: $gateway);

        $this->expectExceptionObject($exception);

        $this->operation->process();
    }

    protected function tearDown(): void
    {
        unset($this->operation);

        parent::tearDown();
    }
}
