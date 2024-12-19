<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\AutopayPaymentMethodNotFound;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class AutopayPaymentMethodNotFoundTest extends UnitTestCase
{
    #[Test]
    public function it_returns_expected_error_message_and_context(): void
    {
        $accountId = Str::uuid()->toString();
        $expectedMessage = __('messages.payment.batch_processing.autopay_method_not_found');
        $expectedContext = ['account_id' => $accountId];

        $exception = new AutopayPaymentMethodNotFound(accountId: $accountId);

        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($expectedContext, $exception->context);
    }
}
