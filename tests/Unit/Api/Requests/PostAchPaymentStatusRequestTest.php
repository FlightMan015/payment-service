<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostAchPaymentStatusRequest;
use Carbon\Carbon;
use Illuminate\Validation\DatabasePresenceVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractApiRequestTest;

class PostAchPaymentStatusRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PostAchPaymentStatusRequest
     */
    public function getTestedRequest(): PostAchPaymentStatusRequest
    {
        return new PostAchPaymentStatusRequest();
    }

    public static function getInvalidData(): array|\Iterator
    {
        yield 'processed_at_from is missing' => [self::getInvalidDataSet(field: 'processed_at_from', value: null)];
        yield 'processed_at_to is missing' => [self::getInvalidDataSet(field: 'processed_at_to', value: null)];
        yield 'processed_at_to is incorrect format' => [
            self::getInvalidDataSet(field: 'processed_at_to', value: Carbon::now()->format('Y-m-d-H:i:s'))
        ];
        yield 'processed_at_to is sooner than processed_at_from' => [
            self::getInvalidDataSet(field: 'processed_at_to', value: Carbon::now()->subDays(2)->format('Y-m-d H:i:s')),
        ];
        yield 'area_ids is not an array' => [self::getInvalidDataSet(field: 'area_ids', value: 1)];
        yield 'area_ids is empty' => [self::getInvalidDataSet(field: 'area_ids', value: [])];
        yield 'area_ids contains non-integer value' => [self::getInvalidDataSet(field: 'area_ids', value: ['a'])];
        yield 'area_ids contains non-existing area id' => [self::getInvalidDataSet(field: 'area_ids', value: [100])];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        yield 'valid data' => [self::getValidDataSet()];
    }

    public static function getValidDataSet(): array
    {
        return [
            'processed_at_from' => Carbon::now()->subDays(1)->format('Y-m-d H:i:s'),
            'processed_at_to' => Carbon::now()->format('Y-m-d H:i:s'),
            'area_ids' => [1],
        ];
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);
        $validator->setPresenceVerifier(presenceVerifier: $this->mockAreaExists());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        if (!empty($data['area_ids'])) {
            $validator->setPresenceVerifier(presenceVerifier: $this->mockAreaExists(isExisting: false));
        }

        $this->assertFalse($validator->passes());
    }

    #[Test]
    public function it_should_include_filter_request_rules(): void
    {
        $request = $this->getTestedRequest();
        $this->assertArrayHasKey('per_page', $request->rules());
        $this->assertArrayHasKey('page', $request->rules());
        $this->assertArrayHasKey('sort', $request->rules());
        $this->assertArrayHasKey('direction', $request->rules());
    }

    private function mockAreaExists(bool $isExisting = true): DatabasePresenceVerifier
    {
        /** @var DatabasePresenceVerifier|MockObject $presenceVerifier */
        $presenceVerifier = $this->createMock(originalClassName: DatabasePresenceVerifier::class);
        $presenceVerifier->method('getCount')->willReturn((int) $isExisting);

        return $presenceVerifier;
    }
}
