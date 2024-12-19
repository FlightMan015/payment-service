<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Validators;

use App\Exceptions\ScheduledPaymentTriggerInvalidMetadataException;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\Validators\InitialServiceCompletedScheduledPaymentTriggerValidator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class InitialServiceCompletedScheduledPaymentTriggerValidatorTest extends UnitTestCase
{
    /** @var SubscriptionServiceInterface&MockObject $subscriptionService */
    private SubscriptionServiceInterface $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = $this->createMock(SubscriptionServiceInterface::class);
    }

    #[Test]
    public function it_throws_exception_if_metadata_does_not_have_subscription_id(): void
    {
        $this->expectException(ScheduledPaymentTriggerInvalidMetadataException::class);
        $this->expectExceptionMessage(__('messages.scheduled_payment.metadata_missing_information', ['property' => 'subscription_id']));

        $validator = new InitialServiceCompletedScheduledPaymentTriggerValidator($this->subscriptionService);
        $validator->validate([]);
    }

    #[Test]
    public function it_throws_exception_if_subscription_is_not_found(): void
    {
        $this->expectException(ScheduledPaymentTriggerInvalidMetadataException::class);
        $this->expectExceptionMessage(__('messages.scheduled_payment.subscription_not_found'));

        $this->subscriptionService->method('getSubscription')->willThrowException(new \Exception());

        $validator = new InitialServiceCompletedScheduledPaymentTriggerValidator($this->subscriptionService);
        $validator->validate(['subscription_id' => Str::uuid()->toString()]);
    }
}
