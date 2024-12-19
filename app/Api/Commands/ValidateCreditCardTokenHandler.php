<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\GatewayInitializationDTO;
use App\Api\DTO\ValidationOperationResultDto;
use App\Api\Exceptions\MissingGatewayException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Factories\PaymentGatewayFactory;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Contracts\Container\BindingResolutionException;
use Money\Currency;
use Money\Money;

class ValidateCreditCardTokenHandler
{
    private ValidateCreditCardTokenCommand|null $command = null;
    private GatewayInterface|null $gateway = null;

    /**
     * @param PaymentProcessor $paymentProcessor
     */
    public function __construct(private readonly PaymentProcessor $paymentProcessor)
    {
    }

    /**
     * @param ValidateCreditCardTokenCommand $command
     *
     * @throws BindingResolutionException
     * @throws MissingGatewayException
     * @throws UnsupportedValueException
     *
     * @return ValidationOperationResultDto
     */
    public function handle(ValidateCreditCardTokenCommand $command): ValidationOperationResultDto
    {
        $this->command = $command;

        $this->getGatewayInstance();
        $this->paymentProcessor->setGateway(gateway: $this->gateway);
        $this->populatePaymentProcessor();

        return new ValidationOperationResultDto(
            isValid: $this->paymentProcessor->authorize(),
            errorMessage: $this->paymentProcessor->getError()
        );
    }

    /**
     * @throws UnsupportedValueException
     * @throws BindingResolutionException
     */
    private function getGatewayInstance(): void
    {
        $this->gateway = PaymentGatewayFactory::make(
            gatewayInitializationDTO: new GatewayInitializationDTO(
                gatewayId: $this->command->gateway->value,
                officeId: $this->command->officeId,
                creditCardToken: $this->command->creditCardToken,
                creditCardExpirationMonth: $this->command->creditCardExpirationMonth,
                creditCardExpirationYear: $this->command->creditCardExpirationYear,
            )
        );
    }

    private function populatePaymentProcessor(): void
    {
        $paymentProcessorData = [
            OperationFields::AMOUNT->value => new Money(amount: 0, currency: new Currency(code: 'USD')),
            OperationFields::CHARGE_DESCRIPTION->value => 'Credit Card Validation',
            OperationFields::CC_EXP_MONTH->value => $this->command->creditCardExpirationMonth,
            OperationFields::CC_EXP_YEAR->value => $this->command->creditCardExpirationYear,
            OperationFields::TOKEN->value => $this->command->creditCardToken,
        ];

        $this->paymentProcessor->populate(populatedData: $paymentProcessorData);
    }
}
