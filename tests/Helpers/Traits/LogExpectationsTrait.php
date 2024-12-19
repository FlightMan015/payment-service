<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

trait LogExpectationsTrait
{
    use MockeryPHPUnitIntegration;

    /**
     * @param string $expectedMessage
     *
     * @return void
     */
    public function expectDebugLog(string $expectedMessage): void
    {
        $this->logger->allows('debug')->withArgs(static fn ($message) => $message === $expectedMessage);
    }

    /**
     * @param string $expectedMessage
     *
     * @return void
     */
    public function expectInfoLog(string $expectedMessage): void
    {
        $this->logger->allows('info')->withArgs(static fn ($message) => $message === $expectedMessage);
    }

    /**
     * @param string $expectedMessage
     *
     * @return void
     */
    public function expectWarningLog(string $expectedMessage): void
    {
        $this->logger->allows('warning')->withArgs(static fn ($message) => $message === $expectedMessage);
    }
}
