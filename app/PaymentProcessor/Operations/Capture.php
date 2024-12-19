<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations;

use App\Helpers\JsonDecoder;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Operations\Validators\CaptureValidator;
use Exception;

class Capture extends AbstractOperation
{
    /**
     * @param GatewayInterface $gateway
     * @param string $validatorClass
     */
    public function __construct(GatewayInterface $gateway, string $validatorClass = CaptureValidator::class)
    {
        parent::__construct(gateway: $gateway);

        $this->validator = new $validatorClass(operation: $this);
    }

    /**
     * @throws InvalidOperationException
     *
     * @return $this
     */
    public function process(): Capture
    {
        try {
            $request = [
                'transaction_id' => $this->getReferenceTransactionId(),
                'amount' => $this->getAmount()?->getAmount(),
                'currency' => $this->getAmount()?->getCurrency(),
                'reference_id' => $this->getReferenceId()
            ];
            $this->setRawRequest(JsonDecoder::encode(value: $request));
            $this->getGateway()->capture(inputData: $request);
        } catch (CreditCardValidationException|InvalidOperationException $e) { // we throw it further to be caught in the handler, but we still want all other exceptions to be caught here
            throw $e;
        } catch (Exception $e) {
            $this->setIsSuccessful(isSuccessful: false);
            $this->setErrorMessage(errorMessage: $e->getMessage());
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function handleResponse(): Capture
    {
        $this->setRawResponse(rawResponse: $this->getGateway()->getResponse())
            ->setErrorMessage(errorMessage: $this->getGateway()->getErrorMessage())
            ->setTransactionId(transactionId: $this->getGateway()->getTransactionId())
            ->setTransactionStatus(transactionStatus: $this->getGateway()->getTransactionStatus());

        $this->setIsSuccessful(isSuccessful: $this->getGateway()->isSuccessful());
        $this->setDeclineReason(declineReason: $this->getGateway()->getDeclineReason());

        $this->logTransaction(transactionTypeId: TransactionTypeEnum::CAPTURE);

        return $this;
    }
}
