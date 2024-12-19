<?php

declare(strict_types=1);

namespace Tests\Stubs\PaymentProcessor;

use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use Money\Currency;

class WorldpayOperationStub
{
    /**
     * @param bool $isAchTransaction
     *
     * @return array
     */
    public static function updatePaymentAccount(bool $isAchTransaction = false): array
    {
        if ($isAchTransaction) {
            return [
                'reference_id' => 'some-ref-id-1',
                'billing_name' => 'Test User',
                'billing_details' => [
                    'address' => [
                        'line1' => '5132 N 300 W',
                        'line2' => '5132 N 300 W',
                        'city' => 'Provo',
                        'state' => 'UT',
                        'postal_code' => '84604',
                    ],
                    'email' => 'ivan.vasechko@goaptive.com'
                ],
            ];
        }

        return [
            'cc_token' => '02',
            'cc_exp_month' => '02',
            'cc_exp_year' => '2024',
            'reference_id' => 'some-ref-id-1',
            'billing_name' => 'Test User',
            'billing_details' => [
                'address' => [
                    'line1' => '5132 N 300 W',
                    'line2' => '5132 N 300 W',
                    'city' => 'Provo',
                    'state' => 'UT',
                    'postal_code' => '84604',
                ],
                'email' => 'ivan.vasechko@goaptive.com'
            ],
        ];
    }

    /**
     * @param bool $isAchTransaction
     * @param bool $withToken
     *
     * @return array
     */
    public static function authCapture(bool $isAchTransaction = false, bool $withToken = false): array
    {
        if ($isAchTransaction) {
            $data = [
                'ach_account_type' => AchAccountTypeEnum::PERSONAL_CHECKING,
                'reference_id' => 'some-ref-id-1',
                'billing_name' => 'Test User',
                'billing_details' => [
                    'address' => [
                        'line1' => '5132 N 300 W',
                        'city' => 'Provo',
                        'state' => 'UT',
                        'postal_code' => '84604'
                    ],
                    'email' => 'ivan.vasechko@goaptive.com'
                ],
                'amount' => 1043,
                'currency' => new Currency(code: 'USD'),
            ];

            $data += $withToken
                ? ['source' => '5555555555554444']
                : [
                    'ach_account_number' => 'P7mZWxSMsL5w5pXNObbFSg36S0+VjcGOSqf5j5tpO+9MTpWasqfQ6ZIJxEr0WTJ7',
                    'ach_routing_number' => '111000025'
                ];

            return $data;
        }

        return [
            'source' => '5555555555554444',
            'cc_exp_month' => '02',
            'cc_exp_year' => '2024',
            'reference_id' => 'some-ref-id-1',
            'billing_name' => 'Test User',
            'billing_details' => [
                'address' => ['line1' => '5132 N 300 W', 'city' => 'Provo', 'state' => 'UT', 'postal_code' => '84604'],
                'email' => 'ivan.vasechko@goaptive.com'
            ],
            'amount' => 1043,
            'currency' => new Currency(code: 'USD'),
        ];
    }

    /**
     * @param bool $isAchTransaction
     *
     * @return array
     */
    public static function cancel(bool $isAchTransaction = false): array
    {
        if ($isAchTransaction) {
            return [
                'transaction_id' => '234845918',
                'reference_id' => 'some-ref-id-1',
                'amount' => 1043,
                'currency' => new Currency(code: 'USD'),
            ];
        }

        return [
            'transaction_id' => '234845918',
            'reference_id' => 'some-ref-id-1',
            'amount' => 1043,
            'currency' => new Currency(code: 'USD'),
        ];
    }

    /**
     * @param bool $isAchTransaction
     *
     * @return array
     */
    public static function credit(bool $isAchTransaction = false): array
    {
        if ($isAchTransaction) {
            return [
                'transaction_id' => '234845918',
                'reference_id' => 'some-ref-id-1',
                'amount' => 1043,
                'currency' => new Currency(code: 'USD'),
            ];
        }

        return [
            'transaction_id' => '234845918',
            'reference_id' => 'some-ref-id-1',
            'amount' => 1043,
            'currency' => new Currency(code: 'USD'),
        ];
    }

    /**
     * @param bool $isAchTransaction
     *
     * @return array
     */
    public static function status(bool $isAchTransaction = false): array
    {
        if ($isAchTransaction) {
            return [
                'transaction_id' => '234845918',
                'reference_id' => 'some-ref-id-1',
            ];
        }

        return [
            'transaction_id' => '234845918',
            'reference_id' => 'some-ref-id-1',
        ];
    }

    /**
     * @param bool $isAchTransaction
     *
     * @return array
     */
    public static function authorize(bool $isAchTransaction = false): array
    {
        if ($isAchTransaction) {
            return [
                'ach_account_number' => '123456789',
                'ach_routing_number' => '111000025',
                'ach_account_type' => AchAccountTypeEnum::PERSONAL_CHECKING,
                'reference_id' => 'some-ref-id-1',
                'billing_name' => 'some-billing-name',
                'billing_details' => [
                    'address' => ['line1' => '5132 N 300 W', 'city' => 'Provo', 'state' => 'UT', 'postal_code' => '84604'],
                    'email' => 'ivan.vasechko@goaptive.com'
                ],
                'amount' => 1043,
                'currency' => new Currency(code: 'USD'),
            ];
        }

        return [
            'transaction_id' => '234845918',
            'reference_id' => 'some-ref-id-1',
            'billing_name' => 'some-billing-name',
            'billing_details' => [
                'address' => ['line1' => '5132 N 300 W', 'city' => 'Provo', 'state' => 'UT', 'postal_code' => '84604'],
                'email' => 'ivan.vasechko@goaptive.com'
            ],
            'amount' => 1043,
            'currency' => new Currency(code: 'USD'),
            'source' => 'test',
            'cc_exp_month' => 12,
            'cc_exp_year' => 2022,
        ];
    }

    /**
     * @param bool $isAchTransaction
     *
     * @return array
     */
    public static function capture(bool $isAchTransaction = false): array
    {
        if ($isAchTransaction) {
            return [
                'transaction_id' => '234845918',
                'reference_id' => 'some-ref-id-1',
                'amount' => 1043,
                'currency' => new Currency(code: 'USD'),
            ];
        }

        return [
            'transaction_id' => '234845918',
            'reference_id' => 'some-ref-id-1',
            'amount' => 1043,
            'currency' => new Currency(code: 'USD'),
        ];
    }
}
