<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostValidateCreditCardTokenRequest;
use App\Rules\GatewayExistsAndActive;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractApiRequestTest;

class PostValidateCreditCardTokenRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PostValidateCreditCardTokenRequest
     */
    public function getTestedRequest(): PostValidateCreditCardTokenRequest
    {
        return new PostValidateCreditCardTokenRequest();
    }

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        if (!empty($data['gateway_id'])) {
            $gatewayExistsActive = Mockery::mock(GatewayExistsAndActive::class)->makePartial();
            $gatewayExistsActive->shouldReceive('passes')->with('gateway_id', $data['gateway_id'])->andReturn(false);

            $validator->setRules(array_merge($this->rules, [
                'gateway_id' => ['bail', 'required', 'integer', $gatewayExistsActive],
            ]));
        }

        $this->assertFalse($validator->passes());
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        if (!empty($data['gateway_id'])) {
            $gatewayExistsActive = Mockery::mock(GatewayExistsAndActive::class)->makePartial();
            $gatewayExistsActive->shouldReceive('passes')->with('gateway_id', $data['gateway_id'])->andReturn(true);

            $validator->setRules(array_merge($this->rules, [
                'gateway_id' => ['bail', 'required', 'integer', $gatewayExistsActive],
            ]));
        }

        $this->assertTrue($validator->passes());
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'missing gateway id' => [
            'data' => [
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 12,
                'cc_expiration_year' => date('Y', strtotime('+20 years')),
            ],
        ];
        yield 'missing office_id when gateway is 1' => [
            'data' => [
                'gateway_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 12,
                'cc_expiration_year' => date('Y', strtotime('+20 years')),
            ],
        ];
        yield 'missing cc_token' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_expiration_month' => 12,
                'cc_expiration_year' => date('Y', strtotime('+20 years')),
            ],
        ];
        yield 'invalid cc_token' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => '@#@#@!#',
                'cc_expiration_month' => 12,
                'cc_expiration_year' => date('Y', strtotime('+20 years')),
            ],
        ];
        yield 'missing year' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 12,
            ],
        ];
        yield 'invalid cc_expiration_month (min-max)' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 123,
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (int)' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 'test',
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (pattern)' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => '012',
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (pattern - 2)' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => '13',
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (from 1->12)' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 13,
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (from 1->12 (2))' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 0,
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_year (int)' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 1,
                'cc_expiration_year' => 'test',
            ],
        ];
        yield 'invalid cc_expiration_year negative value' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 1,
                'cc_expiration_year' => '-11',
            ],
        ];
        yield 'invalid cc_expiration_year (int) 2 digits value' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_month' => 1,
                'cc_expiration_year' => date('y'),
            ],
        ];
        yield 'missing month' => [
            'data' => [
                'gateway_id' => 1,
                'office_id' => 1,
                'cc_token' => 'xxx-xxx-xx-xxxxx',
                'cc_expiration_year' => date('Y', strtotime('next year')),
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

        $input = $fullValidParameters;
        $input['cc_expiration_month'] = '01';
        $input['cc_expiration_year'] = date('Y', strtotime('+2 years'));
        yield 'valid data with full input - 2' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_month'] = '1';
        $input['cc_expiration_year'] = date('Y', strtotime('+2 years'));
        yield 'valid data with full input - 3' => [
            'data' => $input,
        ];

        for ($i = 1; $i <= 10; $i++) {
            $keys = array_keys($fullValidParameters);
            shuffle($keys);
            $input = $fullValidParameters;
            for ($key = 0; $key <= 3; $key++) {
                if (in_array($key, ['cc_expiration_month', 'cc_expiration_year'])) {
                    continue;
                }
                unset($input[$keys[$key]]);
            }
            yield "valid data with some missing-key ($i)" => [
                'data' => $fullValidParameters,
            ];
        }
    }

    public static function getValidDataSet(): array
    {
        return [
            'gateway_id' => 1,
            'office_id' => 1,
            'cc_token' => 'xxx-xxx-xx-xxxxx',
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date('Y', strtotime('+20 years')),
        ];
    }
}
