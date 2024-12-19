<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers\PaymentProcessor;

use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\Gateways\NullGateway;
use App\PaymentProcessor\Operations\AbstractOperation;

class MockedOperation extends AbstractOperation
{
    public const string SUCCESS_RESPONSE = 'Done';
    public const string FAILURE_PROCESS_ERROR_MESSAGE = 'Error';
    public bool $isRunningProcess = false;

    public bool $willProcessFail = false;

    public function __construct()
    {
        parent::__construct(new NullGateway());
    }

    /**
     * @return static
     */
    public function validate(): static
    {
        $this->setIsSuccessful(isSuccessful: true);

        return $this;
    }

    /**
     * @return self
     */
    public function process(): self
    {
        if ($this->willProcessFail) {
            throw new OperationValidationException(errors: [self::FAILURE_PROCESS_ERROR_MESSAGE]);
        }
        $this->isRunningProcess = true;

        $this->setRawResponse(rawResponse: self::SUCCESS_RESPONSE);

        return $this;
    }

    /**
     * @return self
     */
    public function handleResponse(): self
    {
        return $this;
    }
}
