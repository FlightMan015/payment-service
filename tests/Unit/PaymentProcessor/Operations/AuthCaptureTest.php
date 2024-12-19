<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations;

use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Models\Transaction;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\Gateways\NullGateway;
use App\PaymentProcessor\Operations\AuthCapture;
use App\PaymentProcessor\Operations\Validators\AuthCaptureValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\GatewayMockingTrait;
use Tests\Helpers\Traits\RepositoryMockingTrait;
use Tests\Helpers\Traits\ValidatorMockingTrait;
use Tests\Stubs\PaymentProcessor\OperationStub;
use Tests\Unit\UnitTestCase;

class AuthCaptureTest extends UnitTestCase
{
    use GatewayMockingTrait;
    use ValidatorMockingTrait;
    use RepositoryMockingTrait;

    private AuthCapture $operation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operation = OperationStub::authCapture();
    }

    #[Test]
    public function set_up_method_returns_auth_capture_instance(): void
    {
        $this->assertInstanceOf(expected: AuthCapture::class, actual: $this->operation->setUp());
    }

    #[Test]
    public function tear_down_method_returns_auth_capture_instance(): void
    {
        $this->assertInstanceOf(expected: AuthCapture::class, actual: $this->operation->tearDown());
    }

    #[Test]
    public function process_method_sets_raw_request_and_calls_gateway_auth_capture_method(): void
    {
        $this->operation->process();

        $this->assertNotEmpty($this->operation->getRawRequest());
        $this->assertTrue(method_exists($this->operation->getGateway(), 'authCapture'));
    }

    #[Test]
    public function process_method_sets_error_message_if_gateway_throws_exception(): void
    {
        $exceptionMessage = 'Test Error Message';

        $this->operation->setGateway(
            gateway: $this->mockGatewayWillThrowException(
                method: 'authCapture',
                exceptionMessage: $exceptionMessage
            )
        );

        $this->operation->process();

        $this->assertFalse(condition: $this->operation->isSuccessful());
        $this->assertSame(expected: $exceptionMessage, actual: $this->operation->getErrorMessage());
    }

    #[Test]
    public function handle_response_method_sets_raw_response_transaction_id_transaction_status_and_is_successful(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());
        $this->operation->setGateway(gateway: $this->mockGateway());

        $this->operation->handleResponse();

        $this->assertNotEmpty($this->operation->getRawResponse());
        $this->assertNotEmpty($this->operation->getTransactionId());
        $this->assertNotEmpty($this->operation->getTransactionStatus());
        $this->assertTrue($this->operation->isSuccessful());
    }

    #[Test]
    public function handle_response_method_sets_error_message_if_gateway_returns_one(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());
        $this->operation->setGateway(gateway: $this->mockGateway(errorMessage: 'Test Error Message', isSuccessful: false));

        $this->operation->handleResponse();

        $this->assertNotEmpty($this->operation->getRawResponse());
        $this->assertNotEmpty($this->operation->getTransactionId());
        $this->assertNotEmpty($this->operation->getTransactionStatus());
        $this->assertFalse($this->operation->isSuccessful());
        $this->assertNotEmpty($this->operation->getErrorMessage());
    }

    #[Test]
    public function validate_method_throws_operation_validation_exception_if_validator_returns_false(): void
    {
        $this->operation->setValidator(validator: $this->mockFailValidator(className: AuthCaptureValidator::class));

        $this->expectException(OperationValidationException::class);

        $this->operation->validate();
    }

    #[Test]
    public function validate_method_returns_auth_capture_instance_if_validator_returns_true(): void
    {
        $this->operation->setValidator(validator: $this->mockSuccessValidator(className: AuthCaptureValidator::class));

        $this->assertInstanceOf(expected: AuthCapture::class, actual: $this->operation->validate());
    }

    #[Test]
    public function process_method_throws_invalid_operation_exception_when_gateway_throws_invalid_operation_exception(): void
    {
        $exception = new InvalidOperationException(message: 'Some error message here');
        /** @var MockObject|NullGateway */
        $gateway = $this->getMockBuilder(NullGateway::class)->getMock();
        $gateway->method('authCapture')->willThrowException($exception);
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
