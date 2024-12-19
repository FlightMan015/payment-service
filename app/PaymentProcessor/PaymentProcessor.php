<?php

declare(strict_types=1);

namespace App\PaymentProcessor;

use App\Api\Exceptions\MissingGatewayException;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Operations\AuthCapture;
use App\PaymentProcessor\Operations\Authorize;
use App\PaymentProcessor\Operations\Cancel;
use App\PaymentProcessor\Operations\Capture;
use App\PaymentProcessor\Operations\CheckStatus;
use App\PaymentProcessor\Operations\Credit;
use App\PaymentProcessor\Operations\OperationInterface;
use Money\Money;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class PaymentProcessor
{
    use LoggerAwareTrait;

    private GatewayInterface|null $gateway = null;
    private \Exception|null $exception = null;

    public Transaction|null $transactionLog = null;

    /**
     * @param string|null $referenceId
     * @param PaymentTypeEnum|null $paymentType
     * @param string|null $token
     * @param string|null $achToken
     * @param int|null $ccExpYear
     * @param int|null $ccExpMonth
     * @param string|null $achAccountNumber
     * @param string|null $achRoutingNumber
     * @param AchAccountTypeEnum|null $achAccountType
     * @param string|null $nameOnAccount
     * @param string|null $addressLine1
     * @param string|null $addressLine2
     * @param string|null $city
     * @param string|null $province
     * @param string|null $postalCode
     * @param string|null $countryCode
     * @param string|null $emailAddress
     * @param Money|null $amount
     * @param OperationEnum|null $operation
     * @param string|null $responseData
     * @param string|null $requestData
     * @param string|null $error
     * @param string|null $referenceTransactionId
     * @param string|null $chargeDescription
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private string|null $referenceId = null,
        private PaymentTypeEnum|null $paymentType = null,
        private string|null $token = null,
        private string|null $achToken = null,
        private int|null $ccExpYear = null,
        private int|null $ccExpMonth = null,
        private string|null $achAccountNumber = null,
        private string|null $achRoutingNumber = null,
        private AchAccountTypeEnum|null $achAccountType = null,
        private string|null $nameOnAccount = null,
        private string|null $addressLine1 = null,
        private string|null $addressLine2 = null,
        private string|null $city = null,
        private string|null $province = null,
        private string|null $postalCode = null,
        private string|null $countryCode = null,
        private string|null $emailAddress = null,
        private Money|null $amount = null,
        private OperationEnum|null $operation = null,
        private string|null $responseData = null,
        private string|null $requestData = null,
        private string|null $error = null,
        private string|null $referenceTransactionId = null,
        private string|null $chargeDescription = null,
        LoggerInterface|null $logger = null
    ) {
        $this->logger = $logger;
    }

    /**
     * @param array $populatedData
     *
     * @return PaymentProcessor
     */
    public function populate(array $populatedData): PaymentProcessor
    {
        foreach ($populatedData as $key => $inputDatum) {
            match ($key) {
                OperationFields::REFERENCE_ID->value => $this->setReferenceId(referenceId: $inputDatum),
                OperationFields::TOKEN->value => $this->setToken(token: $inputDatum),
                OperationFields::CC_EXP_YEAR->value => $this->setCcExpYear(ccExpYear: $inputDatum),
                OperationFields::CC_EXP_MONTH->value => $this->setCcExpMonth(ccExpMonth: $inputDatum),
                OperationFields::ACH_ACCOUNT_NUMBER->value => $this->setAchAccountNumber(achAccountNumber: $inputDatum),
                OperationFields::ACH_ROUTING_NUMBER->value => $this->setAchRoutingNumber(achRoutingNumber: $inputDatum),
                OperationFields::ACH_ACCOUNT_TYPE->value => $this->setAchAccountType(achAccountType: $inputDatum),
                OperationFields::ACH_TOKEN->value => $this->setAchToken(token: $inputDatum),
                OperationFields::NAME_ON_ACCOUNT->value => $this->setNameOnAccount(nameOnAccount: $inputDatum),
                OperationFields::ADDRESS_LINE_1->value => $this->setAddressLine1(addressLine1: $inputDatum),
                OperationFields::ADDRESS_LINE_2->value => $this->setAddressLine2(addressLine2: $inputDatum),
                OperationFields::CITY->value => $this->setCity(city: $inputDatum),
                OperationFields::PROVINCE->value => $this->setProvince(province: $inputDatum),
                OperationFields::POSTAL_CODE->value => $this->setPostalCode(postalCode: $inputDatum),
                OperationFields::COUNTRY_CODE->value => $this->setCountryCode(countryCode: $inputDatum),
                OperationFields::EMAIL_ADDRESS->value => $this->setEmailAddress(emailAddress: $inputDatum),
                OperationFields::AMOUNT->value => $this->setAmount(amount: $inputDatum),
                OperationFields::REFERENCE_TRANSACTION_ID->value => $this->setReferenceTransactionId(
                    referenceTransactionId: $inputDatum
                ),
                OperationFields::PAYMENT_TYPE->value => $this->setPaymentType(paymentType: $inputDatum),
                OperationFields::CHARGE_DESCRIPTION->value => $this->setChargeDescription(
                    chargeDescription: $inputDatum
                ),
                default => null
            };
        }

        return $this;
    }

    /**
     * @param GatewayInterface $gateway
     */
    public function setGateway(GatewayInterface $gateway): void
    {
        $this->gateway = $gateway;
    }

    /**
     * @throws MissingGatewayException
     *
     * @return bool
     */
    public function sale(): bool
    {
        $this->validateGatewayExist();

        $authCapture = new AuthCapture(gateway: $this->gateway);

        $authCapture->setPaymentType(paymentType: $this->getPaymentType());

        return $this->process(operation: $authCapture);
    }

    /**
     * @throws MissingGatewayException
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $this->validateGatewayExist();

        $authorize = new Authorize(gateway: $this->gateway);

        if (!is_null($this->getPaymentType())) {
            $authorize->setPaymentType(paymentType: $this->getPaymentType());
        }

        return $this->process(operation: $authorize);
    }

    /**
     * @throws MissingGatewayException
     *
     * @return bool
     */
    public function capture(): bool
    {
        $this->validateGatewayExist();

        $capture = new Capture(gateway: $this->gateway);

        $capture->setPaymentType(paymentType: $this->getPaymentType());

        return $this->process(operation: $capture);
    }

    /**
     * @throws MissingGatewayException
     *
     * @return bool
     */
    public function cancel(): bool
    {
        $this->validateGatewayExist();

        $cancel = new Cancel(gateway: $this->gateway);

        $cancel->setPaymentType(paymentType: $this->getPaymentType());

        return $this->process(operation: $cancel);
    }

    /**
     * @throws MissingGatewayException
     *
     * @return bool
     */
    public function void(): bool
    {
        $this->validateGatewayExist();

        return $this->process(operation: new Cancel(gateway: $this->gateway));
    }

    /**
     * @throws MissingGatewayException
     *
     * @return bool
     */
    public function status(): bool
    {
        $this->validateGatewayExist();

        return $this->process(operation: new CheckStatus(gateway: $this->gateway));
    }

    /**
     * @throws MissingGatewayException
     *
     * @return bool
     */
    public function credit(): bool
    {
        $this->validateGatewayExist();

        $credit = new Credit(gateway: $this->gateway);
        $credit->setPaymentType(paymentType: $this->getPaymentType());

        return $this->process(operation: $credit);
    }

    /**
     * @return string|null
     */
    public function getReferenceId(): string|null
    {
        return $this->referenceId;
    }

    /**
     * @param string|null $referenceId
     *
     * @return PaymentProcessor
     */
    public function setReferenceId(string|null $referenceId): PaymentProcessor
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    /**
     * @return PaymentTypeEnum|null
     */
    public function getPaymentType(): PaymentTypeEnum|null
    {
        return $this->paymentType;
    }

    /**
     * @param PaymentTypeEnum|null $paymentType
     *
     * @return PaymentProcessor
     */
    public function setPaymentType(PaymentTypeEnum|null $paymentType): PaymentProcessor
    {
        $this->paymentType = $paymentType;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getToken(): string|null
    {
        return $this->token;
    }

    /**
     * @param string|null $token
     *
     * @return PaymentProcessor
     */
    public function setToken(string|null $token): PaymentProcessor
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getCcExpYear(): int|null
    {
        return $this->ccExpYear;
    }

    /**
     * @param int|null $ccExpYear
     *
     * @return PaymentProcessor
     */
    public function setCcExpYear(int|null $ccExpYear): PaymentProcessor
    {
        $this->ccExpYear = $ccExpYear;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getCcExpMonth(): int|null
    {
        return $this->ccExpMonth;
    }

    /**
     * @param int|null $ccExpMonth
     *
     * @return PaymentProcessor
     */
    public function setCcExpMonth(int|null $ccExpMonth): PaymentProcessor
    {
        $this->ccExpMonth = $ccExpMonth;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAchAccountNumber(): string|null
    {
        return $this->achAccountNumber;
    }

    /**
     * @param string|null $achAccountNumber
     *
     * @return PaymentProcessor
     */
    public function setAchAccountNumber(string|null $achAccountNumber): PaymentProcessor
    {
        $this->achAccountNumber = $achAccountNumber;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAchRoutingNumber(): string|null
    {
        return $this->achRoutingNumber;
    }

    /**
     * @param string|null $achRoutingNumber
     *
     * @return PaymentProcessor
     */
    public function setAchRoutingNumber(string|null $achRoutingNumber): PaymentProcessor
    {
        $this->achRoutingNumber = $achRoutingNumber;

        return $this;
    }

    /**
     * @return AchAccountTypeEnum|null
     */
    public function getAchAccountType(): AchAccountTypeEnum|null
    {
        return $this->achAccountType;
    }

    /**
     * @param AchAccountTypeEnum|null $achAccountType
     *
     * @return PaymentProcessor
     */
    public function setAchAccountType(AchAccountTypeEnum|null $achAccountType): PaymentProcessor
    {
        $this->achAccountType = $achAccountType;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAchToken(): string|null
    {
        return $this->achToken;
    }

    /**
     * @param string|null $token
     *
     * @return PaymentProcessor
     */
    public function setAchToken(string|null $token): PaymentProcessor
    {
        $this->achToken = $token;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getNameOnAccount(): string|null
    {
        return $this->nameOnAccount;
    }

    /**
     * @param string|null $nameOnAccount
     *
     * @return PaymentProcessor
     */
    public function setNameOnAccount(string|null $nameOnAccount): PaymentProcessor
    {
        $this->nameOnAccount = $nameOnAccount;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAddressLine1(): string|null
    {
        return $this->addressLine1;
    }

    /**
     * @param string|null $addressLine1
     *
     * @return PaymentProcessor
     */
    public function setAddressLine1(string|null $addressLine1): PaymentProcessor
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAddressLine2(): string|null
    {
        return $this->addressLine2;
    }

    /**
     * @param string|null $addressLine2
     *
     * @return PaymentProcessor
     */
    public function setAddressLine2(string|null $addressLine2): PaymentProcessor
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCity(): string|null
    {
        return $this->city;
    }

    /**
     * @param string|null $city
     *
     * @return PaymentProcessor
     */
    public function setCity(string|null $city): PaymentProcessor
    {
        $this->city = $city;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getProvince(): string|null
    {
        return $this->province;
    }

    /**
     * @param string|null $province
     *
     * @return PaymentProcessor
     */
    public function setProvince(string|null $province): PaymentProcessor
    {
        $this->province = $province;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPostalCode(): string|null
    {
        return $this->postalCode;
    }

    /**
     * @param string|null $postalCode
     *
     * @return PaymentProcessor
     */
    public function setPostalCode(string|null $postalCode): PaymentProcessor
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCountryCode(): string|null
    {
        return $this->countryCode;
    }

    /**
     * @param string|null $countryCode
     *
     * @return PaymentProcessor
     */
    public function setCountryCode(string|null $countryCode): PaymentProcessor
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * @return Money|null
     */
    public function getAmount(): Money|null
    {
        return $this->amount;
    }

    /**
     * @param Money|null $amount
     *
     * @return PaymentProcessor
     */
    public function setAmount(Money|null $amount): PaymentProcessor
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getEmailAddress(): string|null
    {
        return $this->emailAddress;
    }

    /**
     * @param string|null $emailAddress
     *
     * @return PaymentProcessor
     */
    public function setEmailAddress(string|null $emailAddress): PaymentProcessor
    {
        if (empty($emailAddress)) {
            $this->logger->warning(__('messages.payment.process_without_email'));
        }

        $this->emailAddress = $emailAddress;

        return $this;
    }

    /**
     * @return OperationEnum|null
     */
    public function getOperation(): OperationEnum|null
    {
        return $this->operation;
    }

    /**
     * @param OperationEnum|null $operation
     *
     * @return $this
     */
    public function setOperation(OperationEnum|null $operation): PaymentProcessor
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getResponseData(): string|null
    {
        return $this->responseData;
    }

    /**
     * @param string|null $responseData
     *
     * @return PaymentProcessor
     */
    public function setResponseData(string|null $responseData): PaymentProcessor
    {
        $this->responseData = $responseData;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRequestData(): string|null
    {
        return $this->requestData;
    }

    /**
     * @param string|null $requestData
     *
     * @return PaymentProcessor
     */
    public function setRequestData(string|null $requestData): PaymentProcessor
    {
        $this->requestData = $requestData;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getError(): string|null
    {
        return $this->error;
    }

    /**
     * @param string|null $error
     *
     * @return PaymentProcessor
     */
    public function setError(string|null $error): PaymentProcessor
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getReferenceTransactionId(): string|null
    {
        return $this->referenceTransactionId;
    }

    /**
     * @param string|null $referenceTransactionId
     *
     * @return PaymentProcessor
     */
    public function setReferenceTransactionId(string|null $referenceTransactionId): PaymentProcessor
    {
        $this->referenceTransactionId = $referenceTransactionId;

        return $this;
    }

    private function process(OperationInterface $operation): bool
    {
        $operation->populate(populatedData: [
            'logger' => $this->logger,
            'payment_type' => $this->getPaymentType(),
            'reference_id' => $this->getReferenceId(),
            'token' => $this->getToken(),
            'ach_token' => $this->getAchToken(),
            'cc_exp_month' => $this->getCcExpMonth(),
            'cc_exp_year' => $this->getCcExpYear(),
            'ach_account_number' => $this->getAchAccountNumber(),
            'ach_routing_number' => $this->getAchRoutingNumber(),
            'ach_account_type' => $this->getAchAccountType(),
            'name_on_account' => $this->getNameOnAccount(),
            'address_line_1' => $this->getAddressLine1(),
            'address_line_2' => $this->getAddressLine2(),
            'city' => $this->getCity(),
            'province' => $this->getProvince(),
            'postal_code' => $this->getPostalCode(),
            'country_code' => $this->getCountryCode(),
            'email_address' => $this->getEmailAddress(),
            'amount' => $this->getAmount(),
            'reference_transaction_id' => $this->getReferenceTransactionId(),
            'charge_description' => $this->getChargeDescription(),
        ]);

        $operation->setGateway(gateway: $this->gateway)
            ->setUp()
            ->validate()
            ->process()
            ->handleResponse()
            ->tearDown();

        if (!$operation->isSuccessful()) {
            $this->setError($operation->getErrorMessage());
        }

        $this->transactionLog = $operation->getTransactionLog();

        $this->setResponseData(responseData: $operation->getRawResponse());
        $this->setRequestData(requestData: $operation->getRawRequest());

        return $operation->isSuccessful();
    }

    /**
     * @return string|null
     */
    public function getChargeDescription(): string|null
    {
        return $this->chargeDescription;
    }

    /**
     * @param string|null $chargeDescription
     */
    public function setChargeDescription(string|null $chargeDescription): void
    {
        $this->chargeDescription = $chargeDescription;
    }

    /**
     * @return Transaction|null
     */
    public function getTransactionLog(): Transaction|null
    {
        return $this->transactionLog;
    }

    /**
     * @param \Exception $exception
     *
     * @return void
     */
    public function setException(\Exception $exception): void
    {
        $this->exception = $exception;
    }

    /**
     * @return \Exception|null
     */
    public function getException(): \Exception|null
    {
        return $this->exception;
    }

    /**
     * @return PaymentStatusEnum|null
     */
    public function getGatewayPaymentStatus(): PaymentStatusEnum|null
    {
        return $this->gateway->getPaymentStatus();
    }

    /**
     * @throws MissingGatewayException
     */
    private function validateGatewayExist(): void
    {
        if (empty($this->gateway)) {
            throw new MissingGatewayException();
        }
    }
}
