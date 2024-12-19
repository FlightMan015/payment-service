<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Repositories\CRM;

use App\Api\Repositories\CRM\SubscriptionRepository;
use App\Models\CRM\Customer\Subscription;
use App\Models\PaymentMethod;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class SubscriptionRepositoryTest extends UnitTestCase
{
    #[Test]
    public function set_autopay_payment_method_calls_set_autopay_payment_method_on_subscription_model(): void
    {
        $this->expectNotToPerformAssertions();

        $subscription = Mockery::mock(Subscription::class);
        $paymentMethod = Mockery::mock(PaymentMethod::class);

        $subscription->allows('setAutoPayPaymentMethod')->with($paymentMethod)->andReturns(null);

        (new SubscriptionRepository())->setAutoPayPaymentMethod(
            subscription: $subscription,
            autopayPaymentMethod: $paymentMethod
        );
    }
}
