<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use App\PaymentProcessor\Operations\Validators\ValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;

trait ValidatorMockingTrait
{
    /**
     * @param class-string $className
     */
    private function mockSuccessValidator(string $className): MockObject|ValidatorInterface
    {
        $mockValidator = $this->getMockBuilder(className: $className)->disableOriginalConstructor()->getMock();
        $mockValidator->method('validate')->willReturn(value: true);

        return $mockValidator;
    }

    /**
     * @param class-string $className
     */
    private function mockFailValidator(string $className): MockObject|ValidatorInterface
    {
        $mockValidator = $this->getMockBuilder(className: $className)->disableOriginalConstructor()->getMock();

        $mockValidator->method('validate')->willReturn(value: false);
        $mockValidator->method('getErrors')->willReturn(value: ['error1', 'error2']);

        return $mockValidator;
    }
}
