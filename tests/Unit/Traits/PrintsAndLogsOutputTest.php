<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Traits\PrintsAndLogsOutput;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PrintsAndLogsOutputTest extends UnitTestCase
{
    private mixed $testClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testClass = new class () {
            use PrintsAndLogsOutput;

            public function handleInfo(string $message): void
            {
                $this->printAndLogInfo(message: $message);
            }

            public function handleError(string $message): void
            {
                $this->printAndLogError(message: $message);
            }

            private function info(string $message): void
            {
                echo $message;
            }

            private function error(string $message): void
            {
                echo $message;
            }
        };
    }

    #[Test]
    public function it_logs_and_outputs_info_as_expected(): void
    {
        $message = 'Test Info';

        Log::shouldReceive('info')->once()->with($message);

        $this->testClass->handleInfo(message: $message);

        $this->assertSame($message, $this->getActualOutputForAssertion());
    }

    #[Test]
    public function it_logs_and_outputs_error_as_expected(): void
    {
        $message = 'Test Error';

        Log::shouldReceive('error')->once()->with($message);

        $this->testClass->handleError(message: $message);

        $this->assertSame($message, $this->getActualOutputForAssertion());
    }
}
