<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostAuthorizePaymentRequest;
use App\Models\PaymentMethod;
use App\Rules\AccountExists;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PostAuthorizePaymentRequestTest extends UnitTestCase
{
    /**
     * @return PostAuthorizePaymentRequest
     */
    private function getTestedRequest(): PostAuthorizePaymentRequest
    {
        return new PostAuthorizePaymentRequest();
    }

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make([]);
        if (isset($data['method_id'])) {
            $data['method_id'] = $paymentMethod->id;
        }
        $request = $this->getTestedRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
    }

    public static function getInvalidData(): array|\Iterator
    {
        yield 'empty input' => [[]];
        $validData = [
            'amount' => 1999,
            'account_id' => Str::uuid()->toString(),
            'method_id' => 'METHOD_ID',
            'notes' => 'some notes',
        ];

        $data = $validData;
        unset($data['amount']);
        yield 'empty amount' => [
            [$data],
        ];

        $data = $validData;
        $data['amount'] = 'abc';
        yield 'invalid amount' => [
            [$data],
        ];

        $data = $validData;
        unset($data['method_id']);
        yield 'empty method_id' => [
            [$data],
        ];

        $data = $validData;
        $data['method_id'] = 'abc';
        yield 'invalid method_id' => [
            [$data],
        ];

        $data = $validData;
        $data['notes'] = ['some notes'];
        yield 'invalid notes (array)' => [
            [$data],
        ];
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(['id' => Str::uuid()->toString()]);

        if (isset($data['method_id'])) {
            $data['method_id'] = $paymentMethod->id;
        }
        $request = $this->getTestedRequest();

        $accountExistsRule = $this->createMock(AccountExists::class);
        $accountExistsRule->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExistsRule);
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public static function getValidData(): array|\Iterator
    {
        $validData = [
            'amount' => 1000,
            'account_id' => Str::uuid()->toString(),
            'notes' => 'some notes',
        ];

        yield 'valid data' => [$validData];
    }
}
