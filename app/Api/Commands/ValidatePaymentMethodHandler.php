<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\ValidationOperationResultDto;
use App\Api\Exceptions\InvalidPaymentMethodException;
use App\Api\Traits\PaymentMethodValidationTrait;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Money\Currency;
use Money\Money;

class ValidatePaymentMethodHandler
{
    use PaymentMethodValidationTrait;

    /**
     * @param ValidatePaymentMethodCommand $command
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     *
     * @return ValidationOperationResultDto
     */
    public function handle(ValidatePaymentMethodCommand $command): ValidationOperationResultDto
    {
        $this->paymentMethod = $command->paymentMethod;

        try {
            $this->validatePaymentMethodInGateway();
        } catch (InvalidPaymentMethodException) {
            return new ValidationOperationResultDto(isValid: false, errorMessage: $this->paymentProcessor->getError());
        }

        return new ValidationOperationResultDto(isValid: true);
    }

    /**
     * @throws \Throwable
     */
    protected function populatePaymentProcessor(): void
    {
        $paymentProcessorData = [
            OperationFields::REFERENCE_ID->value => $this->payment->id,
            OperationFields::NAME_ON_ACCOUNT->value => $this->paymentMethod->name_on_account,
            OperationFields::ADDRESS_LINE_1->value => $this->paymentMethod->address_line1,
            OperationFields::ADDRESS_LINE_2->value => $this->paymentMethod->address_line2,
            OperationFields::CITY->value => $this->paymentMethod->city,
            OperationFields::PROVINCE->value => $this->paymentMethod->province,
            OperationFields::POSTAL_CODE->value => $this->paymentMethod->postal_code,
            OperationFields::COUNTRY_CODE->value => $this->paymentMethod->country_code,
            OperationFields::EMAIL_ADDRESS->value => $this->paymentMethod->email,
            OperationFields::CHARGE_DESCRIPTION->value => 'Payment Method Validation',
            OperationFields::AMOUNT->value => new Money(amount: 0, currency: new Currency(code: 'USD')),
            OperationFields::REFERENCE_TRANSACTION_ID->value => $this->payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from(value: $this->payment->payment_type_id),
        ];

        if ($this->payment->payment_type_id === PaymentTypeEnum::CC->value) {
            $paymentProcessorData += [
                OperationFields::CC_EXP_MONTH->value => $this->paymentMethod->cc_expiration_month,
                OperationFields::CC_EXP_YEAR->value => $this->paymentMethod->cc_expiration_year,
                OperationFields::TOKEN->value => $this->paymentMethod->cc_token,
            ];
        } else {
            throw new InvalidOperationException(message: __('messages.payment_method.validate.is_only_supported_for_cc'));
        }

        $this->paymentProcessor->populate(populatedData: $paymentProcessorData);
    }
}
