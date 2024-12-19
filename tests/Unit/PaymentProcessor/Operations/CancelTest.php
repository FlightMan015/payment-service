<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations;

use App\Helpers\JsonDecoder;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\Gateways\NullGateway;
use App\PaymentProcessor\Operations\Cancel;
use App\PaymentProcessor\Operations\Validators\CancelValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\GatewayMockingTrait;
use Tests\Helpers\Traits\ValidatorMockingTrait;
use Tests\Stubs\PaymentProcessor\OperationStub;
use Tests\Unit\UnitTestCase;

class CancelTest extends UnitTestCase
{
    use GatewayMockingTrait;
    use ValidatorMockingTrait;

    private Cancel $operation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operation = OperationStub::cancel();
    }

    #[Test]
    public function process_method_sets_raw_request_and_calls_gateway_cancel_method(): void
    {
        $request = [
            'transaction_id' => $this->operation->getReferenceTransactionId(),
            'amount' => $this->operation->getAmount()->getAmount(),
            'currency' => $this->operation->getAmount()->getCurrency(),
            'reference_id' => $this->operation->getReferenceId(),
        ];

        $gateway = $this->mockGateway();
        $gateway->expects($this->once())->method('cancel')->with($request);

        $this->operation->setGateway(gateway: $gateway);
        $this->operation->process();

        $this->assertSame(JsonDecoder::encode($request), $this->operation->getRawRequest());
    }

    #[Test]
    public function handle_response_method_sets_transaction_id_transaction_status_and_is_successful(): void
    {
        $response = [
            'transaction_id' => '1234',
            'transaction_status' => 'cancelled'
        ];

        $gateway = $this->mockGateway(
            transactionId: $response['transaction_id'],
            transactionStatus: $response['transaction_status']
        );
        $this->operation->setGateway(gateway: $gateway);

        $this->operation->handleResponse();

        $this->assertEquals(expected: $response['transaction_id'], actual: $this->operation->getTransactionId());
        $this->assertEquals(expected: $response['transaction_status'], actual: $this->operation->getTransactionStatus());
        $this->assertTrue($this->operation->isSuccessful());
    }

    #[Test]
    public function process_method_sets_error_message_if_gateway_throws_exception(): void
    {
        $exceptionMessage = 'Test Error Message';

        $this->operation->setGateway(gateway: $this->mockGatewayWillThrowException(
            method: 'cancel',
            exceptionMessage: $exceptionMessage
        ));

        $this->operation->process();

        $this->assertFalse(condition: $this->operation->isSuccessful());
        $this->assertSame(expected: $exceptionMessage, actual: $this->operation->getErrorMessage());
    }

    #[Test]
    public function validate_method_returns_cancel_instance_if_validator_returns_true(): void
    {
        $this->operation->setValidator(validator: $this->mockSuccessValidator(className: CancelValidator::class));

        $this->assertInstanceOf(expected: Cancel::class, actual: $this->operation->validate());
        $this->assertTrue($this->operation->validate()->isSuccessful());
    }

    #[Test]
    public function validate_method_throws_operation_validation_exception_if_validator_returns_false(): void
    {
        $this->operation->setValidator(validator: $this->mockFailValidator(className: CancelValidator::class));

        $this->expectException(OperationValidationException::class);

        $this->operation->validate();
    }

    #[Test]
    public function process_method_throws_invalid_operation_exception_when_gateway_throws_invalid_operation_exception(): void
    {
        $exception = new InvalidOperationException(message: 'Some error message here');
        /** @var MockObject|NullGateway $gateway */
        $gateway = $this->getMockBuilder(NullGateway::class)->getMock();
        $gateway->method('cancel')->willThrowException($exception);
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
        $gateway->method('cancel')->willThrowException($exception);
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
