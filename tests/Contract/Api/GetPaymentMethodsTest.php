<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use App\Rules\AccountExists;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetPaymentMethodsTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payment-methods';

    private array $paymentMethods = [];
    private string $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountId = Str::uuid()->toString();
        $this->mockAccountAlwaysExists();

        $this->paymentMethods = $this->createPaymentMethodsInDatabase();
    }

    #[Test]
    #[DataProvider('validInputProvider')]
    public function it_returns_200_response_for_retrieving_successfully(array $input, int $countDeleted, array $expected): void
    {
        if ($countDeleted > 0) {
            $ids = array_column(array: $this->paymentMethods, column_key: 'id');
            $deletedIds = array_slice(array: $ids, offset: 0, length: $countDeleted);
            PaymentMethod::whereIn(column: 'id', values: $deletedIds)->delete();
        }

        $response = $this->get(
            uri: self::ENDPOINT_URI . '?' . http_build_query(array_merge(['account_id' => $this->accountId], $input)),
            headers: ['Api-Key' => config('auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
                'links' => [
                    'self',
                ],
            ],
            'result' => [
                '*' => [
                    'payment_method_id',
                    'account_id',
                    'type',
                    'date_added',
                    'is_primary',
                    'is_autopay',
                    'description',
                    'gateway' => ['id', 'name'],
                    // we are not asserting cc or ach fields here, since * stands for all objects,
                    // but different objects will have a different set of data depends on `type`
                ],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('_metadata.current_page', $expected['current_page']);
        $response->assertJsonPath('_metadata.per_page', $expected['per_page']);
        $response->assertJsonPath('_metadata.total_pages', $expected['total_pages']);
        $response->assertJsonPath('_metadata.total_results', $expected['total_results']);
        $this->assertCount($expected['count_current_result'], $response->json()['result']);
    }

    #[Test]
    public function it_returns_200_response_for_retrieving_by_multiple_account_ids_successfully(): void
    {
        $account = Account::factory()->create();

        PaymentMethod::factory()->for($account)->count(count: 10)->create([
            'cc_expiration_month' => 10,
            'cc_expiration_year' => date('Y'),
        ]);

        $response = $this->get(
            uri: self::ENDPOINT_URI . '?' . http_build_query(['account_ids' => [$this->accountId, $account->id]]),
            headers: ['Api-Key' => config('auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $this->assertCount(20, $response->json()['result']);
    }

    #[Test]
    #[DataProvider('invalidInputProvider')]
    public function it_returns_400_bad_request_response_for_invalid_input(array $input): void
    {
        $response = $this->get(
            uri: self::ENDPOINT_URI . '?' . http_build_query(array_merge(['account_id' => $this->accountId], $input)),
            headers: ['Api-Key' => config('auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
                'errors' => [
                    '*' => [
                        'detail' => [],
                    ]
                ],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.invalid_input'));
    }

    #[Test]
    public function it_returns_401_response_for_non_api_key_api(): void
    {
        $response = $this->get(uri: self::ENDPOINT_URI);
        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('auth.api_key_not_found'));
    }

    public static function validInputProvider(): \Iterator
    {
        yield 'empty input' => [
            'input' => [],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 1,
                'per_page' => 100,
                'total_pages' => 1,
                'total_results' => 10,
                'count_current_result' => 10,
            ],
        ];
        yield 'empty page' => [
            'input' => [
                'per_page' => 2,
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 1,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 2,
            ],
        ];
        yield 'has page' => [
            'input' => [
                'per_page' => 2,
                'page' => 4,
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 4,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 2,
            ],
        ];
        yield 'has page, no data' => [
            'input' => [
                'per_page' => 2,
                'page' => 6,
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 6,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 0,
            ],
        ];
        yield 'not found data' => [
            'input' => [
                'per_page' => 2,
                'page' => 6,
                'account_id' => Str::uuid()->toString(),
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 6,
                'per_page' => 2,
                'total_pages' => 1,
                'total_results' => 0,
                'count_current_result' => 0,
            ],
        ];
        yield 'has deleted items' => [
            'input' => [
                'per_page' => 2,
                'page' => 1,
            ],
            'countDeleted' => 4,
            'expected' => [
                'current_page' => 1,
                'per_page' => 2,
                'total_pages' => 3,
                'total_results' => 6,
                'count_current_result' => 2,
            ],
        ];
        yield 'date in month, will find all data from that month' => [
            'input' => [
                'per_page' => 10,
                'page' => 1,
                'expire_from_date' => date('Y-10-30'),
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
                'total_results' => 10,
                'count_current_result' => 10,
            ],
        ];
        yield 'date in month, will find all data to that month' => [
            'input' => [
                'per_page' => 10,
                'page' => 1,
                'expire_to_date' => date('Y-10-12'),
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
                'total_results' => 10,
                'count_current_result' => 10,
            ],
        ];
        yield 'no data from that date' => [
            'input' => [
                'per_page' => 10,
                'page' => 1,
                'cc_expire_from_date' => date('Y-10-12', strtotime('+ 10 years')),
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
                'total_results' => 0,
                'count_current_result' => 0,
            ],
        ];
        yield 'no data to that date' => [
            'input' => [
                'per_page' => 10,
                'page' => 1,
                'cc_expire_to_date' => date('Y-10-12', strtotime('- 10 years')),
            ],
            'countDeleted' => 0,
            'expected' => [
                'current_page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
                'total_results' => 0,
                'count_current_result' => 0,
            ],
        ];
    }

    public static function invalidInputProvider(): \Iterator
    {
        yield 'wrong page' => [
            'input' => [
                'page' => 'asd',
            ],
        ];
        yield 'wrong per page' => [
            'input' => [
                'per_page' => 'asd',
            ],
        ];
        yield 'missing account id' => [
            'input' => [
                'account_id' => '',
            ],
        ];
        yield 'invalid account id' => [
            'input' => [
                'account_id' => 'aaa',
            ],
        ];
        yield 'invalid from date format' => [
            'input' => [
                'cc_expire_from_date' => 'aaa',
            ],
        ];
        yield 'from date exists but empty' => [
            'input' => [
                'cc_expire_from_date' => '',
            ],
        ];
        yield 'invalid to date format' => [
            'input' => [
                'cc_expire_to_date' => 'aaa',
            ],
        ];
        yield 'to date exists but empty' => [
            'input' => [
                'cc_expire_to_date' => '',
            ],
        ];
    }

    private function createPaymentMethodsInDatabase(int $count = 10): array
    {
        return PaymentMethod::factory()
            ->for(Account::factory()->create(['id' => $this->accountId]))
            ->count(count: $count)
            ->create([
                'cc_expiration_month' => 10,
                'cc_expiration_year' => date('Y'),
            ])
            ->toArray();
    }

    private function mockAccountAlwaysExists(): void
    {
        $accountExists = $this->createMock(AccountExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExists);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentMethods, $this->accountId);
    }
}
