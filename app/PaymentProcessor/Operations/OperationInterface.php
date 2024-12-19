<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations;

use App\Models\Transaction;
use App\PaymentProcessor\Gateways\GatewayInterface;

interface OperationInterface
{
    /**
     * @return self
     */
    public function setUp(): self;

    /**
     * @return self
     */
    public function tearDown(): self;

    /**
     * @param array $populatedData
     *
     * @return self
     */
    public function populate(array $populatedData): self;

    /**
     * @return self
     */
    public function process(): self;

    /**
     * @return self
     */
    public function handleResponse(): self;

    /**
     * @return self
     */
    public function validate(): self;

    /**
     * @return string|null
     */
    public function getRawResponse(): string|null;

    /**
     * @return string|null
     */
    public function getRawRequest(): string|null;
    /**
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * @return Transaction|null
     */
    public function getTransactionLog(): Transaction|null;

    /**
     * @return string|null
     */
    public function getErrorMessage(): string|null;

    /**
     * @param GatewayInterface $gateway
     *
     * @return self
     */
    public function setGateway(GatewayInterface $gateway): self;
}
