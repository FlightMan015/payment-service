<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Exceptions;

use App\PaymentProcessor\Exceptions\GatewayDeclineReasonUnmapped;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class GatewayDeclineReasonUnmappedTest extends UnitTestCase
{
    #[Test]
    public function it_generates_expected_error_message(): void
    {
        $responseCode = 123;
        $exception = new GatewayDeclineReasonUnmapped($responseCode);
        $this->assertEquals(
            __('messages.gateway.unmapped_decline_reason', ['code' => $responseCode]),
            $exception->getMessage()
        );
    }
}
