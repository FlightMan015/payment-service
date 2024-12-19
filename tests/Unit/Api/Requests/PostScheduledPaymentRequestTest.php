<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostScheduledPaymentRequest;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use App\Rules\AccountExists;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractApiRequestTest;

class PostScheduledPaymentRequestTest extends AbstractApiRequestTest
{
    private const string MOCK_EXISTING_ACCOUNT_ID = '7ad5b397-9cc7-46fe-a7ba-07bb45427e32';
    private const string MOCK_EXISTING_METHOD_ID = '7ad5b397-9cc7-46fe-a7ba-07bb45427e12';

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $expectedErrorsCount = $data['expectedErrorsCount'];
        unset($data['expectedErrorsCount']);

        $this->mockAccountExists(data: $data);
        $this->setRules();
        $validator = $this->makeValidator(data: $data, rules: $this->rules);
        $validator->setPresenceVerifier(presenceVerifier: $this->mockPaymentMethodExists(data: $data));

        $this->assertTrue($validator->fails());
        $this->assertCount($expectedErrorsCount, $validator->errors());
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $this->mockAccountExists(data: $data);
        $this->setRules();
        $validator = $this->makeValidator(data: $data, rules: $this->rules);
        $validator->setPresenceVerifier(presenceVerifier: $this->mockPaymentMethodExists(data: $data));

        $this->assertTrue($validator->passes());
    }

    /**
     * @return PostScheduledPaymentRequest
     */
    public function getTestedRequest(): PostScheduledPaymentRequest
    {
        return new PostScheduledPaymentRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'empty_request' => [
            'data' => [
                'expectedErrorsCount' => 4
            ],
        ];
        yield 'null_amount' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => null,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'less_then_min_amount' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => -1,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'null_payment_method' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'non_uuid_method_id' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => 123,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'not_exist_payment_method' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => Str::uuid()->toString(),
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'null_account_id' => [
            'data' => [
                'account_id' => null,
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'non_uuid_account_id' => [
            'data' => [
                'account_id' => 'non-uuid',
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'invalid_account_id' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'non_existing_account_id' => [
            'data' => [
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'null_trigger_id' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => null,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'invalid_trigger_id' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => -12313,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'non_integer_trigger_id' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => 'non-integer',
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'no_trigger_id' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'invalid_metadata_as_string' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'metadata' => '123a some notes',
                'expectedErrorsCount' => 1,
            ],
        ];
        yield 'non_array_metadata' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'method_id' => self::MOCK_EXISTING_METHOD_ID,
                'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
                'metadata' => 'non-array',
                'expectedErrorsCount' => 1,
            ],
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
            'amount' => 100,
            'method_id' => self::MOCK_EXISTING_METHOD_ID,
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'metadata' => ['123a some notes'],
        ];
    }

    private function mockAccountExists(array $data): void
    {
        $mockIsAccountExists = !empty($data['account_id'])
            && $data['account_id'] === self::MOCK_EXISTING_ACCOUNT_ID;

        $mock = $this->createMock(originalClassName: AccountExists::class);
        $mock->method('passes')->willReturn($mockIsAccountExists);
        $this->app->instance(abstract: AccountExists::class, instance: $mock);
    }

    private function mockPaymentMethodExists(array $data): DatabasePresenceVerifier
    {
        $exists = data_get($data, 'method_id') === self::MOCK_EXISTING_METHOD_ID;
        /** @var DatabasePresenceVerifier|MockObject $presenceVerifier */
        $presenceVerifier = $this->createMock(originalClassName: DatabasePresenceVerifier::class);
        $presenceVerifier->method('getCount')->willReturn($exists ? 1 : 0);

        return $presenceVerifier;
    }
}
