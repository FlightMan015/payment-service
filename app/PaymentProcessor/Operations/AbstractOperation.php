<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations;

use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Operations\Validators\ValidatorInterface;
use Illuminate\Support\Str;
use Money\Money;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractOperation implements OperationInterface
{
    use LoggerAwareTrait;

    private PaymentTypeEnum|null $paymentType = null;
    private string|null $token = null;
    private string|null $achToken = null;
    private int|null $ccExpYear = null;
    private int|null $ccExpMonth = null;
    private string|null $achAccountNumber = null;
    private string|null $achRoutingNumber = null;
    private AchAccountTypeEnum|null $achAccountType = null;
    private string|null $nameOnAccount = null;
    private string|null $addressLine1 = null;
    private string|null $addressLine2 = null;
    private string|null $city = null;
    private string|null $province = null;
    private string|null $postalCode = null;
    private string|null $countryCode = null;
    private string|null $emailAddress = null;
    private Money|null $amount = null;
    private string|null $chargeDescription = null;
    private string|null $referenceId = null;
    private bool $isSuccessful = false;
    private DeclineReasonEnum|null $declineReason = null;
    private string|null $rawRequest = null;
    private string|null $rawResponse = null;
    private string|null $errorMessage = null;
    private string|null $transactionId = null;
    private string|null $transactionStatus = null;
    private string|null $referenceTransactionId = null;
    protected ValidatorInterface $validator;

    public Transaction|null $transaction = null;

    /**
     * @param GatewayInterface $gateway
     */
    public function __construct(protected GatewayInterface $gateway)
    {
    }

    /**
     * @return static
     */
    public function setUp(): static
    {
        return $this;
    }

    /**
     * @return static
     */
    public function tearDown(): static
    {
        return $this;
    }

    /**
     * @param array $populatedData
     *
     * @return static
     */
    public function populate(array $populatedData): static
    {
        foreach ($populatedData as $key => $inputDatum) {
            if (property_exists($this, Str::camel($key))) {
                $this->{Str::camel($key)} = $inputDatum;
            }
        }

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
     * @param PaymentTypeEnum $paymentType
     *
     * @return static
     */
    public function setPaymentType(PaymentTypeEnum $paymentType): static
    {
        $this->paymentType = $paymentType;
        $this->gateway->setPaymentType(paymentType: $paymentType);

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
     * @return AbstractOperation
     */
    public function setAchAccountNumber(string|null $achAccountNumber): AbstractOperation
    {
        $this->achAccountNumber = $achAccountNumber;

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
     * @return AbstractOperation
     */
    public function setToken(string|null $token): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setCcExpYear(int|null $ccExpYear): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setCcExpMonth(int|null $ccExpMonth): AbstractOperation
    {
        $this->ccExpMonth = $ccExpMonth;

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
     * @return AbstractOperation
     */
    public function setAchRoutingNumber(string|null $achRoutingNumber): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setAchAccountType(AchAccountTypeEnum|null $achAccountType): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setAchToken(string|null $token): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setNameOnAccount(string|null $nameOnAccount): AbstractOperation
    {
        $this->nameOnAccount = $nameOnAccount;

        return $this;
    }

    /**
     * @param string|null $addressLine1
     * @param string|null $addressLine2
     * @param string|null $city
     * @param string|null $province
     * @param string|null $postalCode
     * @param string|null $countryCode
     *
     * @return AbstractOperation
     */
    public function setAddress(
        string|null $addressLine1,
        string|null $addressLine2,
        string|null $city,
        string|null $province,
        string|null $postalCode,
        string|null $countryCode
    ): AbstractOperation {
        $this->setAddressLine1(addressLine1: $addressLine1);
        $this->setAddressLine2(addressLine2: $addressLine2);
        $this->setCity(city: $city);
        $this->setProvince(province: $province);
        $this->setPostalCode(postalCode: $postalCode);
        $this->setCountryCode(countryCode: $countryCode);

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
     * @return AbstractOperation
     */
    public function setAddressLine1(string|null $addressLine1): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setAddressLine2(string|null $addressLine2): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setCity(string|null $city): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setProvince(string|null $province): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setPostalCode(string|null $postalCode): AbstractOperation
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
     * @return AbstractOperation
     */
    public function setCountryCode(string|null $countryCode): AbstractOperation
    {
        $this->countryCode = $countryCode;

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
     * @return AbstractOperation
     */
    public function setEmailAddress(string|null $emailAddress): AbstractOperation
    {
        $this->emailAddress = $emailAddress;

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
     * @return AbstractOperation
     */
    public function setAmount(Money|null $amount): AbstractOperation
    {
        $this->amount = $amount;

        return $this;
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
     *
     * @return AbstractOperation
     */
    public function setChargeDescription(string|null $chargeDescription): AbstractOperation
    {
        $this->chargeDescription = $chargeDescription;

        return $this;
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
     * @return AbstractOperation
     */
    public function setReferenceId(string|null $referenceId): AbstractOperation
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    /**
     * @param bool $isSuccessful
     *
     * @return AbstractOperation
     */
    public function setIsSuccessful(bool $isSuccessful): AbstractOperation
    {
        $this->isSuccessful = $isSuccessful;

        return $this;
    }

    public function setDeclineReason(DeclineReasonEnum|null $declineReason): AbstractOperation
    {
        $this->declineReason = $declineReason;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRawRequest(): string|null
    {
        return $this->rawRequest;
    }

    /**
     * @param string|null $rawRequest
     *
     * @return AbstractOperation
     */
    public function setRawRequest(string|null $rawRequest): AbstractOperation
    {
        $this->rawRequest = $rawRequest;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRawResponse(): string|null
    {
        return $this->rawResponse;
    }

    /**
     * @param string|null $rawResponse
     *
     * @return AbstractOperation
     */
    public function setRawResponse(string|null $rawResponse): AbstractOperation
    {
        $this->rawResponse = $rawResponse;

        return $this;
    }

    /**
     * @return GatewayInterface
     */
    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }

    /**
     * @param GatewayInterface $gateway
     *
     * @return AbstractOperation
     */
    public function setGateway(GatewayInterface $gateway): AbstractOperation
    {
        $this->gateway = $gateway;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getErrorMessage(): string|null
    {
        return $this->errorMessage;
    }

    /**
     * @param string|null $errorMessage
     *
     * @return AbstractOperation
     */
    public function setErrorMessage(string|null $errorMessage): AbstractOperation
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTransactionId(): string|null
    {
        return $this->transactionId;
    }

    /**
     * @param string|null $transactionId
     *
     * @return AbstractOperation
     */
    public function setTransactionId(string|null $transactionId): AbstractOperation
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTransactionStatus(): string|null
    {
        return $this->transactionStatus;
    }

    /**
     * @param string|null $transactionStatus
     *
     * @return AbstractOperation
     */
    public function setTransactionStatus(string|null $transactionStatus): AbstractOperation
    {
        $this->transactionStatus = $transactionStatus;

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
     * @return AbstractOperation
     */
    public function setReferenceTransactionId(string|null $referenceTransactionId): AbstractOperation
    {
        $this->referenceTransactionId = $referenceTransactionId;

        return $this;
    }

    /**
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * @return static
     */
    public function validate(): static
    {
        if (!$this->validator->validate()) {
            $this->setIsSuccessful(isSuccessful: false);

            throw new OperationValidationException(errors: $this->validator->getErrors());
        }

        $this->setIsSuccessful(isSuccessful: true);

        return $this;
    }

    protected function logTransaction(TransactionTypeEnum $transactionTypeId): Transaction|null
    {
        // TODO: make sure every single implementing of this abstract has reference transaction id, then can remove this if
        if (
            !$this->getReferenceId()
            || !preg_match(pattern: '/' . OperationFields::REFERENCE_ID_REGEX . '/', subject: $this->getReferenceId())
            || empty($this->getGateway()->getTransactionId())
        ) {
            return $this->transaction = null;
        }

        $transactionRepository = app()->make(PaymentTransactionRepository::class);

        return $this->transaction = $transactionRepository->create(attributes: [
            'payment_id' => $this->getReferenceId(),
            'transaction_type_id' => $transactionTypeId,
            'raw_request_log' => $this->getRawRequest(),
            'raw_response_log' => $this->getRawResponse(),
            'gateway_transaction_id' => $this->getGateway()->getTransactionId(),
            'gateway_response_code' => $this->getGateway()->getTransactionStatus(),
            'decline_reason_id' => $this->declineReason?->value,
        ]);
    }

    /**
     * @return Transaction|null
     */
    public function getTransactionLog(): Transaction|null
    {
        return $this->transaction;
    }
}
