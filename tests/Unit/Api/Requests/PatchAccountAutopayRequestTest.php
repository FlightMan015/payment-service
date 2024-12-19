<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PatchAccountAutopayRequest;
use App\Rules\AccountExists;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractApiRequestTest;

class PatchAccountAutopayRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PatchAccountAutopayRequest
     */
    public function getTestedRequest(): PatchAccountAutopayRequest
    {
        $this->mockAccountAlwaysExists();
        return new PatchAccountAutopayRequest();
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
        yield 'missing account_id' => [
            'data' => [],
        ];

        yield 'invalid account_id (int)' => [
            'data' => [
                'account_id' => 123,
            ],
        ];

        yield 'invalid autopay_method_id (int)' => [
            'data' => [
                'autopay_method_id' => 12345,
                'account_id' => Str::uuid()->toString(),
            ],
        ];

        yield 'invalid autopay_method_id (array)' => [
            'data' => [
                'autopay_method_id' => [1234, 123],
                'account_id' => Str::uuid()->toString(),
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
            'account_id' => Str::uuid()->toString(),
            'autopay_method_id' => Str::uuid()->toString(),
        ];
    }

    private function mockAccountAlwaysExists(): void
    {
        $accountExists = $this->createMock(AccountExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExists);
    }

    private function mockPaymentMethodExists(): DatabasePresenceVerifier
    {
        /** @var DatabasePresenceVerifier|MockObject $presenceVerifier */
        $presenceVerifier = $this->createMock(originalClassName: DatabasePresenceVerifier::class);
        $presenceVerifier->method('getCount')->willReturn(1);

        return $presenceVerifier;
    }
}
