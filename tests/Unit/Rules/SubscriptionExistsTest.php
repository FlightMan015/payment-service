<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Api\Repositories\CRM\SubscriptionRepository;
use App\Rules\SubscriptionExists;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class SubscriptionExistsTest extends UnitTestCase
{
    #[Test]
    public function valid_subscription_exists_and_rule_passes(): void
    {
        $rule = new SubscriptionExists(
            $this->mockSubscriptionRepositoryWithResult(method: 'exists', expectedResult: true)
        );

        $this->assertTrue(condition: $rule->passes(attribute: 'subscription_id', value: Str::uuid()->toString()));
    }

    #[Test]
    public function invalid_subscription_does_not_exist_and_rule_does_not_pass(): void
    {
        $rule = new SubscriptionExists(
            $this->mockSubscriptionRepositoryWithResult(method: 'exists', expectedResult: false)
        );
        $this->assertFalse(condition: $rule->passes(attribute: 'subscription_id', value: Str::uuid()->toString()));
    }

    #[Test]
    public function validation_error_message_returns_as_expected(): void
    {
        $rule = new SubscriptionExists(
            $this->mockSubscriptionRepositoryWithResult(method: 'exists', expectedResult: false)
        );

        $this->assertEquals(expected: __('messages.subscription.not_found_in_db'), actual: $rule->message());
    }

    private function mockSubscriptionRepositoryWithResult(string $method, mixed $expectedResult): SubscriptionRepository
    {
        /**
         * @var SubscriptionRepository|MockObject $subscriptionRepository
         */
        $subscriptionRepository = $this->createMock(originalClassName: SubscriptionRepository::class);
        $subscriptionRepository->method($method)->willReturn($expectedResult);

        return $subscriptionRepository;
    }
}
