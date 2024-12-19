<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PatchSubscriptionAutopayStatusRequest;
use App\Rules\SubscriptionExists;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractApiRequestTest;

class PatchSubscriptionAutopayStatusRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PatchSubscriptionAutopayStatusRequest
     */
    public function getTestedRequest(): PatchSubscriptionAutopayStatusRequest
    {
        $this->mockAccountAlwaysExists();

        return new PatchSubscriptionAutopayStatusRequest();
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);
        $validator->setPresenceVerifier(presenceVerifier: $this->mockPaymentMethodExists());

        $this->assertTrue($validator->passes());
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'missing subscription_id' => [
            'data' => [],
        ];

        yield 'invalid subscription_id (int)' => [
            'data' => [
                'subscription_id' => 123,
            ],
        ];

        yield 'invalid autopay_method_id (int)' => [
            'data' => [
                'autopay_method_id' => 12345,
                'subscription_id' => Str::uuid()->toString(),
            ],
        ];

        yield 'invalid autopay_method_id (array)' => [
            'data' => [
                'autopay_method_id' => [1234, 123],
                'subscription_id' => Str::uuid()->toString(),
            ],
        ];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();
        yield 'valid data with full input' => [
            'data' => $fullValidParameters,
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'subscription_id' => Str::uuid()->toString(),
            'autopay_method_id' => Str::uuid()->toString(),
        ];
    }

    private function mockAccountAlwaysExists(): void
    {
        $accountExists = $this->createMock(SubscriptionExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: SubscriptionExists::class, instance: $accountExists);
    }

    private function mockPaymentMethodExists(): DatabasePresenceVerifier
    {
        /** @var DatabasePresenceVerifier|MockObject $presenceVerifier */
        $presenceVerifier = $this->createMock(originalClassName: DatabasePresenceVerifier::class);
        $presenceVerifier->method('getCount')->willReturn(1);

        return $presenceVerifier;
    }
}
