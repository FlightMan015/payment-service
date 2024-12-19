<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations;

use App\Helpers\JsonDecoder;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Operations\Validators\AuthorizeValidator;

class Authorize extends AbstractOperation
{
    /**
     * @param GatewayInterface $gateway
     * @param string $validatorClass
     */
    public function __construct(GatewayInterface $gateway, string $validatorClass = AuthorizeValidator::class)
    {
        parent::__construct(gateway: $gateway);

        $this->validator = new $validatorClass(operation: $this);
    }

    /**
     * @param PaymentTypeEnum|null $paymentType
     *
     * @return static
     */
    public function setUp(PaymentTypeEnum|null $paymentType = null): static
    {
        if (!is_null($paymentType)) {
            $this->setPaymentType(paymentType: $paymentType);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function process(): Authorize
    {
        try {
            $request = [
                'amount' => $this->getAmount()?->getAmount(),
                'currency' => $this->getAmount()?->getCurrency(),
                'description' => $this->getChargeDescription(),
                'source' => $this->getToken(),
                'cc_exp_year' => $this->getCcExpYear(),
                'cc_exp_month' => $this->getCcExpMonth(),
                'ach_account_number' => $this->getAchAccountNumber(),
                'ach_routing_number' => $this->getAchRoutingNumber(),
                'ach_account_type' => $this->getAchAccountType(),
                'billing_name' => $this->getNameOnAccount(),
                'reference_id' => $this->getReferenceId(),
                'billing_details' => [
                    'address' => [
                        'line1' => $this->getAddressLine1(),
                        'line2' => $this->getAddressLine2(),
                        'city' => $this->getCity(),
                        'state' => $this->getProvince(),
                        'postal_code' => $this->getPostalCode()
                    ],
                    'email' => $this->getEmailAddress()
                ]
            ];

            $this->setRawRequest(JsonDecoder::encode(value: $request));
            $this->getGateway()->authorize(inputData: $request);
        } catch (CreditCardValidationException|InvalidOperationException $e) { // we throw it further to be caught in the handler, but we still want all other exceptions to be caught here
            throw $e;
        } catch (\Exception $e) {
            $this->setIsSuccessful(isSuccessful: false);
            $this->setErrorMessage(errorMessage: $e->getMessage());
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function handleResponse(): Authorize
    {
        $this->setRawResponse(rawResponse: $this->getGateway()->getResponse())
            ->setErrorMessage(errorMessage: $this->getGateway()->getErrorMessage())
            ->setTransactionId(transactionId: $this->getGateway()->getTransactionId())
            ->setTransactionStatus(transactionStatus: $this->getGateway()->getTransactionStatus());

        $this->setIsSuccessful(isSuccessful: $this->getGateway()->isSuccessful());
        $this->setDeclineReason(declineReason: $this->getGateway()->getDeclineReason());

        $this->logTransaction(transactionTypeId: TransactionTypeEnum::AUTHORIZE);

        return $this;
    }
}
