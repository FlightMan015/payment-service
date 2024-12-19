<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\PaymentMethodValidationTrait;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Money\Currency;
use Money\Money;

class UpdatePaymentMethodHandler
{
    use PaymentMethodValidationTrait;
    use RetrieveGatewayForPaymentMethodTrait;

    private UpdatePaymentMethodCommand|null $command = null;

    /**
     * @param PaymentProcessor $paymentProcessor
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly PaymentRepository $paymentRepository
    ) {
    }

    /**
     * @param PaymentMethod $paymentMethod
     * @param UpdatePaymentMethodCommand $command
     *
     * @throws PaymentValidationException
     * @throws UnprocessableContentException
     * @throws UnsupportedValueException
     * @throws BindingResolutionException
     * @throws \Throwable
     *
     * @return bool
     */
    public function handle(PaymentMethod $paymentMethod, UpdatePaymentMethodCommand $command): bool
    {
        $this->command = $command;
        $this->paymentMethod = $paymentMethod;
        $this->getGatewayInstanceBasedOnPaymentMethod();

        Log::shareContext(context: [
            'payment_method_id' => $this->paymentMethod->id,
            'account_id' => $this->paymentMethod->account_id,
            'request' => $this->command->toArray(),
        ]);

        $this->validateUpdateByPaymentType();

        try {
            DB::transaction(callback: function () {
                $this->updatePaymentMethod();

                if ($this->command->isPrimary && !$this->paymentMethod->is_primary) {
                    $this->paymentMethod->makePrimary();
                }
                if ($this->command->isPrimary === false && $this->paymentMethod->is_primary) {
                    $this->paymentMethod->unsetPrimary();
                }

                if ($this->paymentMethod->payment_type_id !== PaymentTypeEnum::ACH->value) {
                    $this->validatePaymentMethodInGateway();
                }

                // we update payment method in Tokenex, it's valid only for WorldPay
                if ($this->paymentMethod->payment_gateway_id === PaymentGatewayEnum::WORLDPAY->value) {
                    $this->updatePaymentMethodInGateway();
                }
            });
        } catch (\Exception $e) {
            throw new UnprocessableContentException(message: $e->getMessage());
        }

        return true;
    }

    /**
     * @throws PaymentValidationException
     */
    private function validateUpdateByPaymentType(): void
    {
        if ($this->paymentMethod->payment_type_id === PaymentTypeEnum::ACH->value) {
            if (!empty($this->command->creditCardExpirationMonth) || !empty($this->command->creditCardExpirationYear)) {
                throw new PaymentValidationException(message: __('messages.payment_method.update.cannot_update_cc_fields_on_ach'));
            }
        }
    }

    private function updatePaymentMethod(): void
    {
        $this->paymentMethodRepository->update(
            paymentMethod: $this->paymentMethod,
            attributes: array_filter($this->command->toArray())
        );
    }

    /**
     * @throws \Throwable
     */
    protected function populatePaymentProcessor(): void
    {
        $paymentProcessorData = [
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
            OperationFields::REFERENCE_ID->value => $this->payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($this->paymentMethod->payment_type_id),
            OperationFields::CC_EXP_MONTH->value => $this->paymentMethod->cc_expiration_month,
            OperationFields::CC_EXP_YEAR->value => $this->paymentMethod->cc_expiration_year,
            OperationFields::TOKEN->value => $this->paymentMethod->cc_token,
        ];

        $this->paymentProcessor->populate(populatedData: $paymentProcessorData);
    }

    /**
     * @throws UnprocessableContentException
     * @throws \Throwable
     */
    private function updatePaymentMethodInGateway(): void
    {
        $this->gateway->updatePaymentAccount(
            paymentAccountId: $this->getPaymentAccountIdFromGateway(),
            paymentMethod: $this->paymentMethod
        );

        throw_unless(
            condition: $this->gateway->isSuccessful(),
            exception: new UnprocessableContentException(
                message: __('messages.payment_method.update.cannot_update_payment_account', ['error' => $this->gateway->getErrorMessage()])
            )
        );
    }

    /**
     * @throws UnprocessableContentException
     */
    private function getPaymentAccountIdFromGateway(): string
    {
        if (!empty($this->paymentMethod->cc_token)) {
            $paymentAccount = $this->gateway->getPaymentAccount(paymentAccountId: $this->paymentMethod->cc_token);

            if (!empty($paymentAccount)) {
                return $paymentAccount->PaymentAccountID;
            }
        }

        throw new UnprocessableContentException(message: __('messages.payment_method.update.cannot_retrieve_payment_account'));
    }
}
