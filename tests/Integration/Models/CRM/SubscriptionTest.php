<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Models\CRM;

use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException;
use App\Models\CRM\Customer\Subscription;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_sets_autopay_payment_method_if_provided(): void
    {
        $subscription = Subscription::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'account_id' => $subscription->account->id
        ]);

        $subscription->setAutoPayPaymentMethod($paymentMethod);
        $this->assertDatabaseHas(
            table: 'billing.subscription_autopay_payment_methods',
            data: [
                'subscription_id' => $subscription->id,
                'payment_method_id' => $paymentMethod->id,
            ]
        );
    }

    #[Test]
    public function it_unsets_autopay_payment_method_if_it_is_null(): void
    {
        $subscription = Subscription::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'account_id' => $subscription->account->id
        ]);
        $subscription->paymentMethod()->sync($paymentMethod);

        $subscription->setAutoPayPaymentMethod(null);
        $this->assertDatabaseMissing(
            table: 'billing.subscription_autopay_payment_methods',
            data: [
                'subscription_id' => $subscription->id,
                'payment_method_id' => $paymentMethod->id,
            ]
        );
    }

    #[Test]
    public function it_throws_exception_if_payment_method_does_not_belong_to_account_which_subscription_is_associated_to(): void
    {
        $subscription = Subscription::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        $this->expectException(PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException::class);

        $subscription->setAutoPayPaymentMethod($paymentMethod);
    }
}
