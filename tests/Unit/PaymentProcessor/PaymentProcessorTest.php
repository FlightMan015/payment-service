<?php

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor;

use App\Api\Exceptions\MissingGatewayException;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Gateways\Worldpay;
use App\PaymentProcessor\Operations\AuthCapture;
use App\PaymentProcessor\Operations\Authorize;
use App\PaymentProcessor\Operations\Cancel;
use App\PaymentProcessor\Operations\Capture;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Helpers\Traits\RepositoryMockingTrait;
use Tests\Stubs\PaymentProcessor\PaymentProcessorStub;
use Tests\Unit\UnitTestCase;

class PaymentProcessorTest extends UnitTestCase
{
    use RepositoryMockingTrait;

    private LoggerInterface|MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::spy($this->createMock(originalClassName: LoggerInterface::class));
    }

    #[Test]
    #[DataProvider('populateProvider')]
    public function populate_modifies_class_data_and_return_its_class(array $input): void
    {
        $paymentProcessor = new PaymentProcessor();
        $result = $paymentProcessor->populate(populatedData: $input);

        $this->assertInstanceOf(PaymentProcessor::class, $result);

        foreach ($input as $key => $value) {
            $fn = 'get' . Str::studly($key);

            $this->assertEquals($value, $result->$fn());
        }
    }

    public static function populateProvider(): \Iterator
    {
        yield 'Empty data' => [
            'input' => [],
        ];

        $modifiedData = [
            OperationFields::REFERENCE_ID->value => 'Updated',
        ];

        yield 'One item' => [
            'input' => $modifiedData,
        ];

        $modifiedData = [
            OperationFields::REFERENCE_ID->value => 'Updated',
            OperationFields::TOKEN->value => 'Updated',
            OperationFields::CC_EXP_YEAR->value => 2000,
            OperationFields::CC_EXP_MONTH->value => 12,
            OperationFields::ACH_ACCOUNT_NUMBER->value => 'Updated',
            OperationFields::ACH_ROUTING_NUMBER->value => 'Updated',
            OperationFields::ACH_ACCOUNT_TYPE->value => AchAccountTypeEnum::PERSONAL_SAVINGS,
            OperationFields::NAME_ON_ACCOUNT->value => 'Updated',
            OperationFields::ADDRESS_LINE_1->value => 'Updated',
            OperationFields::ADDRESS_LINE_2->value => 'Updated',
            OperationFields::CITY->value => 'Updated',
            OperationFields::PROVINCE->value => 'Updated',
            OperationFields::POSTAL_CODE->value => 'Updated',
            OperationFields::COUNTRY_CODE->value => 'Updated',
            OperationFields::EMAIL_ADDRESS->value => 'Updated',
            OperationFields::AMOUNT->value => new Money(amount: 2000, currency: new Currency(code: 'AUD')),
            OperationFields::REFERENCE_TRANSACTION_ID->value => 'Updated',
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::CHECK,
            OperationFields::CHARGE_DESCRIPTION->value => 'Updated',
        ];

        yield 'Full item' => [
            'input' => $modifiedData,
        ];
    }

    #[Test]
    public function sale_runs_through_process(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(true);

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->sale();

        $operation = new AuthCapture(gateway: $gateway);

        $this->assertTrue($result);
        $this->assertInstanceOf(AuthCapture::class, $operation);
    }

    #[Test]
    public function authorize_runs_through_process(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(true);

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->authorize();

        $operation = new Authorize(gateway: $gateway);

        $this->assertTrue($result);
        $this->assertInstanceOf(Authorize::class, $operation);
        $this->assertNull($paymentProcessor->getTransactionLog());
    }

    #[Test]
    public function authorize_with_unsuccessful_response_populates_error_message(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(false);
        $gateway->method('getErrorMessage')->willReturn('Test error message here');

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->authorize();

        $operation = new Authorize(gateway: $gateway);

        $this->assertFalse($result);
        $this->assertInstanceOf(Authorize::class, $operation);

        $this->assertSame('Test error message here', $paymentProcessor->getError());
    }

    #[Test]
    public function capture_runs_through_process(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(true);

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->capture();

        $operation = new Capture(gateway: $gateway);

        $this->assertTrue($result);
        $this->assertInstanceOf(Capture::class, $operation);
        $this->assertNull($paymentProcessor->getTransactionLog());
    }

    #[Test]
    public function capture_with_unsuccessful_response_populates_error_message(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(false);
        $gateway->method('getErrorMessage')->willReturn('Test error message here');

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->capture();

        $operation = new Capture(gateway: $gateway);

        $this->assertFalse($result);
        $this->assertInstanceOf(Capture::class, $operation);

        $this->assertSame('Test error message here', $paymentProcessor->getError());
    }

    #[Test]
    public function cancel_runs_through_process(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(true);

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->cancel();

        $operation = new Cancel(gateway: $gateway);

        $this->assertTrue($result);
        $this->assertInstanceOf(Cancel::class, $operation);
        $this->assertNull($paymentProcessor->getTransactionLog());
    }

    #[Test]
    public function cancel_with_unsuccessful_response_populates_error_message(): void
    {
        $this->repositoryWillReturn(repositoryClass: PaymentTransactionRepository::class, method: 'create', value: Transaction::factory()->withoutRelationships()->make());

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(false);
        $gateway->method('getErrorMessage')->willReturn('Test error message here');

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->cancel();

        $operation = new Cancel(gateway: $gateway);

        $this->assertFalse($result);
        $this->assertInstanceOf(Cancel::class, $operation);

        $this->assertSame('Test error message here', $paymentProcessor->getError());
    }

    #[Test]
    public function void_runs_through_process(): void
    {
        Transaction::saving(static fn () => false);

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(true);

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->void();

        $this->assertTrue($result);
    }

    #[Test]
    public function status_runs_through_process(): void
    {
        Transaction::saving(static fn () => false);

        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(true);

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->status();

        $this->assertTrue($result);
    }

    #[Test]
    public function credit_returns_true_as_expected(): void
    {
        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $gateway = $this->createMock(Worldpay::class);
        /* @var MockObject $gateway */
        $gateway->method('isSuccessful')->willReturn(true);

        /* @var Worldpay $gateway */
        $paymentProcessor->setGateway(gateway: $gateway);
        $result = $paymentProcessor->credit();

        $this->assertTrue($result);
    }

    #[Test]
    #[DataProvider('gatewayNotSetProvider')]
    public function it_will_through_exception_when_gateway_is_not_set(string $method): void
    {
        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $this->expectException(MissingGatewayException::class);
        $this->expectExceptionMessage(__('messages.gateway.missing'));
        $paymentProcessor->$method();
    }

    public static function gatewayNotSetProvider(): array
    {
        return [
            ['sale'],
            ['authorize'],
            ['capture'],
            ['cancel'],
            ['void'],
            ['status'],
            ['credit'],
        ];
    }

    #[Test]
    public function set_operation_should_return_itself(): PaymentProcessor
    {
        $paymentProcessor = new PaymentProcessor();

        $result = $paymentProcessor->setOperation(OperationEnum::AUTH_CAPTURE);

        $this->assertInstanceOf(PaymentProcessor::class, $result);

        return $result;
    }

    #[Test]
    #[Depends('set_operation_should_return_itself')]
    public function get_operation_return_what_was_set_in_above_test(PaymentProcessor $paymentProcessor): void
    {
        $operation = $paymentProcessor->getOperation();
        $this->assertInstanceOf(OperationEnum::class, $operation);
        $this->assertSame(OperationEnum::AUTH_CAPTURE, $operation);
    }

    #[Test]
    public function get_response_data_return_expected_result(): void
    {
        $paymentProcessor = new PaymentProcessor();

        $paymentProcessor->setResponseData(responseData: null);
        $this->assertNull($paymentProcessor->getResponseData());

        $paymentProcessor->setResponseData(responseData: 'Something here');
        $this->assertSame('Something here', $paymentProcessor->getResponseData());
    }

    #[Test]
    #[DataProvider('emptyEmailProvider')]
    public function id_adds_warning_log_if_email_is_not_provided(string|null $email): void
    {
        $paymentProcessor = PaymentProcessorStub::make(logger: $this->logger);

        $paymentProcessor->setEmailAddress(emailAddress: $email);
        $this->logger->shouldHaveReceived('warning')->once()->with(__('messages.payment.process_without_email'));
    }

    public static function emptyEmailProvider(): iterable
    {
        yield 'Null email' => ['email' => null];
        yield 'Empty email' => ['email' => ''];
    }
}
