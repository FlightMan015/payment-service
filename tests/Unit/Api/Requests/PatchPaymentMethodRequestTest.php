<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PatchPaymentMethodRequest;
use Tests\Helpers\AbstractApiRequestTest;

class PatchPaymentMethodRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PatchPaymentMethodRequest
     */
    public function getTestedRequest(): PatchPaymentMethodRequest
    {
        return new PatchPaymentMethodRequest();
    }

    /** @inheritDoc  */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid first name (includes backtick)' => [
            'data' => [
                'first_name' => 'John`'
            ],
        ];
        yield 'invalid address (includes backtick)' => [
            'data' => [
                'address_line1' => 'Address`'
            ],
        ];
        yield 'invalid email' => [
            'data' => [
                'email' => 'non-email'
            ],
        ];
        yield 'invalid country_code' => [
            'data' => [
                'country_code' => 'SAMPLE'
            ],
        ];
        yield 'missing year' => [
            'data' => [
                'cc_expiration_month' => 12,
            ],
        ];
        yield 'invalid cc_expiration_month (min-max)' => [
            'data' => [
                'cc_expiration_month' => 123,
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (int)' => [
            'data' => [
                'cc_expiration_month' => 'test',
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (pattern)' => [
            'data' => [
                'cc_expiration_month' => '012',
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (pattern - 2)' => [
            'data' => [
                'cc_expiration_month' => '13',
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (from 1->12)' => [
            'data' => [
                'cc_expiration_month' => 13,
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_month (from 1->12 (2))' => [
            'data' => [
                'cc_expiration_month' => 0,
                'cc_expiration_year' => date('Y', strtotime('next year')),
            ],
        ];
        yield 'invalid cc_expiration_year (int)' => [
            'data' => [
                'cc_expiration_month' => 1,
                'cc_expiration_year' => 'test',
            ],
        ];
        yield 'invalid cc_expiration_year negative value' => [
            'data' => [
                'cc_expiration_month' => 1,
                'cc_expiration_year' => '-11',
            ],
        ];
        yield 'invalid cc_expiration_year (int) 2 digits value' => [
            'data' => [
                'cc_expiration_month' => 1,
                'cc_expiration_year' => date('y'),
            ],
        ];
        yield 'missing month' => [
            'data' => [
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
            'first_name' => 'First',
            'last_name' => 'Last Name',
            'description' => 'Some description',
            'address_line1' => '322',
            'address_line2' => 'Broadway',
            'address_line3' => 'Non-line',
            'email' => 'sample@email.sample',
            'city' => 'Kingston',
            'province' => 'GA',
            'postal_code' => '12401',
            'country_code' => 'US',
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date('Y', strtotime('+20 years')),
        ];
    }
}
