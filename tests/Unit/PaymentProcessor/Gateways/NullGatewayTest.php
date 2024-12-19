<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Gateways;

use App\Models\PaymentMethod;
use App\PaymentProcessor\Gateways\NullGateway;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class NullGatewayTest extends UnitTestCase
{
    private NullGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new NullGateway();
    }

    #[Test]
    public function it_returns_false_for_auth_capture(): void
    {
        $this->assertFalse(condition: $this->gateway->authCapture(inputData: []));
    }

    #[Test]
    public function it_returns_false_for_authorize(): void
    {
        $this->assertFalse(condition: $this->gateway->authorize(inputData: []));
    }

    #[Test]
    public function it_returns_false_for_cancel(): void
    {
        $this->assertFalse(condition: $this->gateway->cancel(inputData: []));
    }

    #[Test]
    public function it_returns_false_for_status(): void
    {
        $this->assertFalse(condition: $this->gateway->status(inputData: []));
    }

    #[Test]
    public function it_returns_false_for_credit(): void
    {
        $this->assertFalse(condition: $this->gateway->credit(inputData: []));
    }

    #[Test]
    public function it_returns_false_for_capture(): void
    {
        $this->assertFalse(condition: $this->gateway->capture(inputData: []));
    }

    #[Test]
    public function it_returns_an_empty_std_class_for_parse_response(): void
    {
        $this->assertEmpty(actual: get_object_vars(object: $this->gateway->parseResponse()));
    }

    #[Test]
    public function it_returns_an_empty_string_for_get_transaction_id(): void
    {
        $this->assertEmpty(actual: $this->gateway->getTransactionId());
    }

    #[Test]
    public function it_returns_an_empty_string_for_get_transaction_status(): void
    {
        $this->assertEmpty(actual: $this->gateway->getTransactionStatus());
    }

    #[Test]
    public function it_returns_false_for_is_successful(): void
    {
        $this->assertFalse(condition: $this->gateway->isSuccessful());
    }

    #[Test]
    public function it_returns_null_for_get_payment_account(): void
    {
        $this->assertNull(actual: $this->gateway->getPaymentAccount(paymentAccountId: 12345));
    }

    #[Test]
    public function it_returns_true_for_update_payment_account(): void
    {
        $this->assertTrue($this->gateway->updatePaymentAccount(
            paymentAccountId: Str::uuid()->toString(),
            paymentMethod: new PaymentMethod()
        ));
    }

    #[Test]
    public function it_returns_true_for_create_transaction_set_up(): void
    {
        $this->assertNull($this->gateway->createTransactionSetup(
            referenceId: 123,
            callbackUrl: '',
        ));
    }

    #[Test]
    public function generate_transaction_setup_url_returns_null(): void
    {
        $this->assertNull($this->gateway->generateTransactionSetupUrl(
            transactionSetupId: 123
        ));
    }

    protected function tearDown(): void
    {
        unset($this->gateway);

        parent::tearDown();
    }
}
