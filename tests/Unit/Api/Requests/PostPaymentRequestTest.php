<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostPaymentRequest;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Rules\AccountExists;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractApiRequestTest;

class PostPaymentRequestTest extends AbstractApiRequestTest
{
    private const string MOCK_EXISTING_ACCOUNT_ID = '7ad5b397-9cc7-46fe-a7ba-07bb45427e32';

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $this->mockAccountExists(data: $data);
        $this->setRules();
        $validator = $this->makeValidator(data: $data, rules: $this->rules);

        $this->assertTrue($validator->fails());
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $this->mockAccountExists(data: $data);
        $this->setRules();
        $validator = $this->makeValidator(data: $data, rules: $this->rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * @return PostPaymentRequest
     */
    public function getTestedRequest(): PostPaymentRequest
    {
        return new PostPaymentRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'empty_request' => [
            'data' => []
        ];
        yield 'null_amount' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => null,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => '2021-01-01',
            ]
        ];
        yield 'less_then_min_amount' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 0,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => '2021-01-01',
            ]
        ];
        yield 'null_payment_type' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'type' => null,
                'check_date' => '2021-01-01',
            ]
        ];
        yield 'invalid_payment_type' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'type' => PaymentTypeEnum::CC->name,
                'check_date' => '2021-01-01',
            ]
        ];
        yield 'null_account_id' => [
            'data' => [
                'account_id' => null,
                'amount' => 100,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => '2021-01-01',
            ]
        ];
        yield 'invalid_account_id' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'amount' => 100,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => '2021-01-01',
            ]
        ];
        yield 'non_existing_account_id' => [
            'data' => [
                'amount' => 100,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => '2021-01-01',
            ]
        ];
        yield 'null_check_date_for_check_payment' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => null,
            ]
        ];
        yield 'invalid_check_date_for_check_payment' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => '1234567890',
            ]
        ];
        yield 'invalid_notes_as_array' => [
            'data' => [
                'account_id' => self::MOCK_EXISTING_ACCOUNT_ID,
                'amount' => 100,
                'type' => PaymentTypeEnum::CHECK->name,
                'check_date' => '2021-01-01',
                'notes' => ['123a'],
            ]
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
            'type' => PaymentTypeEnum::CHECK->name,
            'check_date' => '2021-01-01',
            'notes' => '123a some notes',
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
}
