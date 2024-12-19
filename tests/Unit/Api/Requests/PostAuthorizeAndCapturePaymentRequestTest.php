<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostAuthorizeAndCapturePaymentRequest;
use App\Rules\AccountExists;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractApiRequestTest;

class PostAuthorizeAndCapturePaymentRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PostAuthorizeAndCapturePaymentRequest
     */
    public function getTestedRequest(): PostAuthorizeAndCapturePaymentRequest
    {
        $this->mockAccountAlwaysExists();

        return new PostAuthorizeAndCapturePaymentRequest();
    }

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);
        if (!empty($data['method_id']) || !empty($data['recurring_payment_id'])) {
            /** @var DatabasePresenceVerifier|MockObject */
            $presenceVerifier = $this->getMockBuilder(className: DatabasePresenceVerifier::class)
                ->disableOriginalConstructor()
                ->getMock();
            $presenceVerifier->method('getCount')->willReturn(1);
            $validator->setPresenceVerifier(presenceVerifier: $presenceVerifier);
        }

        $this->assertFalse($validator->passes());
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid amount (string)' => [
            'data' => [
                'amount' => 'John\n',
                'account_id' => Str::uuid()->toString(),
            ],
        ];
        yield 'invalid account_id (string)' => [
            'data' => [
                'amount' => 44444,
                'account_id' => 'some string',
            ],
        ];
        yield 'missing amount' => [
            'data' => [
                'account_id' => Str::uuid()->toString()
            ],
        ];
        yield 'missing account_id' => [
            'data' => [
                'amount' => 123
            ],
        ];
        yield 'invalid method_id (string)' => [
            'data' => [
                'amount' => 44444,
                'account_id' => Str::uuid()->toString(),
                'method_id' => '123a',
            ],
        ];
        yield 'invalid method_id (array)' => [
            'data' => [
                'amount' => 44444,
                'account_id' => Str::uuid()->toString(),
                'method_id' => ['123a'],
            ],
        ];
        yield 'invalid method_id (float)' => [
            'data' => [
                'amount' => 44444,
                'account_id' => Str::uuid()->toString(),
                'method_id' => 5.78,
            ],
        ];
        yield 'invalid notes (array)' => [
            'data' => [
                'amount' => 44444,
                'account_id' => Str::uuid()->toString(),
                'notes' => ['123a'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);
        if (!empty($data['method_id']) || !empty($data['recurring_payment_id'])) {
            /** @var DatabasePresenceVerifier|MockObject $presenceVerifier */
            $presenceVerifier = $this->getMockBuilder(className: DatabasePresenceVerifier::class)
                ->disableOriginalConstructor()
                ->getMock();
            $presenceVerifier->method('getCount')->willReturn(1);
            $validator->setPresenceVerifier(presenceVerifier: $presenceVerifier);
        }

        $this->assertTrue($validator->passes());
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();
        yield 'valid data with full input' => [
            'data' => $fullValidParameters,
        ];

        $input = $fullValidParameters;
        unset($input['method_id']);
        yield 'valid data without method_id' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['recurring_payment_id']);
        yield 'valid data without recurring_payment_id' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['invoice_id']);
        yield 'valid data without invoice_id' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['notes']);
        yield 'valid data without notes' => [
            'data' => $input,
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'amount' => 100,
            'account_id' => Str::uuid()->toString(),
            'method_id' => Str::uuid()->toString(),
            'notes' => 'some - notes \n',
        ];
    }

    private function mockAccountAlwaysExists(): void
    {
        $accountExists = $this->createMock(AccountExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExists);
    }
}
