<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\ValidateCreditCardTokenCommand;
use App\Api\Commands\ValidateCreditCardTokenHandler;
use App\Api\DTO\ValidationOperationResultDto;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\PaymentProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class ValidateCreditCardTokenHandlerTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWorldPayCredentialsRepository();
    }

    #[Test]
    public function handle_method_returns_true_if_the_gateway_returns_true_for_authorization(): void
    {
        $command = new ValidateCreditCardTokenCommand(
            gateway: PaymentGatewayEnum::WORLDPAY,
            officeId: 1,
            creditCardToken: 'xx-xxx-21312',
            creditCardExpirationMonth: 12,
            creditCardExpirationYear: 2013,
        );

        /** @var MockObject|PaymentProcessor $paymentProcessor */
        $paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $paymentProcessor->method('authorize')->willReturn(value: true);

        $this->assertEquals(
            expected: new ValidationOperationResultDto(isValid: true, errorMessage: null),
            actual: $this->handler(paymentProcessor: $paymentProcessor)->handle(command: $command)
        );

        $this->assertSame([
            'gateway_id' => $command->gateway->value,
            'office_id' => $command->officeId,
            'cc_token' => $command->creditCardToken,
            'cc_expiration_month' => $command->creditCardExpirationMonth,
            'cc_expiration_year' => $command->creditCardExpirationYear,
        ], $command->toArray());
    }

    #[Test]
    public function handle_method_returns_dto_with_error_message_if_the_gateway_returns_false_for_authorization(): void
    {
        $command = new ValidateCreditCardTokenCommand(
            gateway: PaymentGatewayEnum::WORLDPAY,
            officeId: 1,
            creditCardToken: 'xx-xxx-21312',
            creditCardExpirationMonth: 12,
            creditCardExpirationYear: 2013,
        );

        /** @var MockObject|PaymentProcessor $paymentProcessor */
        $paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $paymentProcessor->method('authorize')->willReturn(value: false);
        $errorMessage = __('messages.operation.something_went_wrong');
        $paymentProcessor->method('getError')->willReturn($errorMessage);

        $this->handler(paymentProcessor: $paymentProcessor)->handle(command: $command);

        $this->assertEquals(
            expected: new ValidationOperationResultDto(isValid: false, errorMessage: $errorMessage),
            actual: $this->handler(paymentProcessor: $paymentProcessor)->handle(command: $command)
        );
    }

    private function handler(PaymentProcessor $paymentProcessor): ValidateCreditCardTokenHandler
    {
        return new ValidateCreditCardTokenHandler(
            paymentProcessor: $paymentProcessor,
        );
    }
}
