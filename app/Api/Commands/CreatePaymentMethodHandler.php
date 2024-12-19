<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\CreatePaymentMethodResultDto;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\ServerErrorException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\PaymentMethodValidationTrait;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Models\CRM\Customer\Account;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Money\Currency;
use Money\Money;

class CreatePaymentMethodHandler
{
    use PaymentMethodValidationTrait;
    use RetrieveGatewayForPaymentMethodTrait;

    private CreatePaymentMethodCommand|null $command = null;
    private Account $account;

    /**
     * @param AccountRepository $accountRepository
     * @param PaymentProcessor $paymentProcessor
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly PaymentRepository $paymentRepository
    ) {
    }

    /**
     * @param CreatePaymentMethodCommand $command
     *
     * @throws UnprocessableContentException
     * @throws ServerErrorException
     * @throws \Throwable
     *
     * @return CreatePaymentMethodResultDto
     */
    public function handle(CreatePaymentMethodCommand $command): CreatePaymentMethodResultDto
    {
        $this->command = $command;

        Log::shareContext(context: [
            'account_id' => $this->command->accountId,
            'request' => $this->command->toArray(),
        ]);

        try {
            $this->findAccount();
        } catch (ResourceNotFoundException $exception) {
            throw new UnprocessableContentException($exception->getMessage());
        }

        DB::transaction(callback: function () {
            $this->createPaymentMethod();
            $this->getGatewayInstanceBasedOnPaymentMethod();
            if (!$this->command->shouldSkipGatewayValidation && $this->command->type !== PaymentTypeEnum::ACH) {
                $this->validatePaymentMethodInGateway();
            }
        });

        return new CreatePaymentMethodResultDto(paymentMethodId: $this->paymentMethod->id);
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function findAccount(): void
    {
        $account = $this->accountRepository->find(id: $this->command->accountId);

        if (is_null($account)) {
            throw new ResourceNotFoundException(message: __('messages.account.not_found_by_id', ['id' => $this->command->accountId]));
        }

        $this->account = $account;
    }

    private function createPaymentMethod(): void
    {
        $this->paymentMethod = $this->paymentMethodRepository->create(attributes: [
            'external_ref_id' => null,
            'account_id' => $this->account->id,
            'payment_gateway_id' => $this->command->gateway->value,
            'payment_type_id' => $this->command->type,

            'ach_account_number_encrypted' => $this->command->type === PaymentTypeEnum::ACH ? $this->command->achAccountNumber : null,
            'ach_routing_number' => $this->command->type === PaymentTypeEnum::ACH ? $this->command->achRoutingNumber : null,
            'ach_account_type' => $this->command->type === PaymentTypeEnum::ACH ? $this->command->achAccountType->value : null,
            'ach_bank_name' => $this->command->type === PaymentTypeEnum::ACH ? $this->command->achBankName : null,
            'ach_token' => null,

            'cc_token' => $this->command->type !== PaymentTypeEnum::ACH ? $this->command->creditCardToken : null,
            'cc_type' => $this->command->type !== PaymentTypeEnum::ACH ? $this->command->creditCardType?->value : null,
            'cc_expiration_month' => $this->command->type !== PaymentTypeEnum::ACH ? $this->command->creditCardExpirationMonth : null,
            'cc_expiration_year' => $this->command->type !== PaymentTypeEnum::ACH ? $this->command->creditCardExpirationYear : null,

            'last_four' => match ($this->command->type) {
                PaymentTypeEnum::CC => $this->command->creditCardLastFour,
                PaymentTypeEnum::ACH => $this->command->achAccountLastFour,
                default => null,
            },

            'name_on_account' => implode(separator: ' ', array: array_values([$this->command->firstName, $this->command->lastName])),
            'address_line1' => $this->command->addressLine1,
            'address_line2' => $this->command->addressLine2,
            'email' => $this->command->email,
            'city' => $this->command->city,
            'province' => $this->command->province,
            'postal_code' => $this->command->postalCode,
            'country_code' => $this->command->countryCode,
        ]);

        if ($this->command->isPrimary) {
            $this->paymentMethodRepository->makePrimary($this->paymentMethod);
        }

        Log::shareContext(context: ['payment_method_id' => $this->paymentMethod->id]);
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
            OperationFields::CHARGE_DESCRIPTION->value => $this->paymentMethod->description ?? __('messages.payment_method.validate.validation_description'),
            OperationFields::AMOUNT->value => new Money(amount: 0, currency: new Currency(code: 'USD')),
            OperationFields::REFERENCE_TRANSACTION_ID->value => $this->payment->id,
            OperationFields::PAYMENT_TYPE->value => $this->command->type,
        ];

        if ($this->command->type === PaymentTypeEnum::ACH) {
            $paymentProcessorData += [
                OperationFields::ACH_ACCOUNT_NUMBER->value => $this->command->achAccountNumber,
                OperationFields::ACH_ROUTING_NUMBER->value => $this->command->achRoutingNumber,
                OperationFields::ACH_ACCOUNT_TYPE->value => $this->command->achAccountType,
            ];
        } else {
            $paymentProcessorData += [
                OperationFields::CC_EXP_MONTH->value => $this->command->creditCardExpirationMonth,
                OperationFields::CC_EXP_YEAR->value => $this->command->creditCardExpirationYear,
                OperationFields::TOKEN->value => $this->command->creditCardToken,
            ];
        }

        $this->paymentProcessor->populate(populatedData: $paymentProcessorData);
    }
}
