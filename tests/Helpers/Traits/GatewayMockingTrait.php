<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Gateways\NullGateway;
use PHPUnit\Framework\MockObject\MockObject;

trait GatewayMockingTrait
{
    /**
     * @param class-string $className
     * @param string $response
     * @param string|null $errorMessage
     * @param string $transactionId
     * @param string $transactionStatus
     * @param bool $isSuccessful
     */
    private function mockGateway(
        string $className = NullGateway::class,
        string $response = 'Test Response',
        string|null $errorMessage = null,
        string $transactionId = 'Test Transaction ID',
        string $transactionStatus = 'Test Transaction Status',
        bool $isSuccessful = true,
    ): GatewayInterface|MockObject {
        $mockGateway = $this->createMock(originalClassName: $className);

        $mockGateway->method('getResponse')->willReturn(value: $response);
        $mockGateway->method('getErrorMessage')->willReturn(value: $errorMessage);
        $mockGateway->method('getTransactionId')->willReturn(value: $transactionId);
        $mockGateway->method('getTransactionStatus')->willReturn(value: $transactionStatus);
        $mockGateway->method('isSuccessful')->willReturn(value: $isSuccessful);

        return $mockGateway;
    }

    /**
     * @param string $method
     * @param class-string $className
     * @param string $exceptionClass
     * @param string $exceptionMessage
     */
    private function mockGatewayWillThrowException(
        string $method,
        string $className = NullGateway::class,
        string $exceptionClass = \RuntimeException::class,
        string $exceptionMessage = 'Something went wrong'
    ): GatewayInterface|MockObject {
        $mockGateway = $this->getMockBuilder(className: $className)->getMock();
        $mockGateway->method($method)->willThrowException(new $exceptionClass(message: $exceptionMessage));

        return $mockGateway;
    }
}
