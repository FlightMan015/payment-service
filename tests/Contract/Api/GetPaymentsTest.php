<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Rules\AccountExists;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetPaymentsTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments';

    private string $accountId;
    /** @var Collection<int, Payment> $payments */
    private Collection $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountId = Str::uuid()->toString();
        $this->payments = $this->createPaymentsInDatabase();
    }

    /**
     * @param int $count
     *
     * @return Collection<int, Payment>|Payment
     */
    private function createPaymentsInDatabase(int $count = 10): Collection|Payment
    {
        return Payment::factory()->count(count: $count)->for(Account::factory()->create(attributes: ['id' => $this->accountId]))->create();
    }

    #[Test]
    public function it_return_401_response_for_non_api_key_api(): void
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

    #[Test]
    #[DataProvider('validInputProvider')]
    public function it_returns_json_get_multiple_response_for_retrieving_successfully(array $input, array $expected): void
    {
        $accountExists = $this->createMock(AccountExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExists);

        $response = $this->get(
            uri: self::ENDPOINT_URI . '?' . http_build_query(array_merge(['account_id' => $this->accountId], $input)),
            headers: ['Api-Key' => config('auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
                'links' => [
                    'self' => [],
                ],
            ],
            'result' => [
                '*' => [
                    'payment_id',
                    'status',
                    'amount',
                    'created_at',
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
    public function it_returns_json_response_when_sorting_and_filter_by_first_name(): void
    {
        $accountExists = $this->createMock(AccountExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExists);

        $response = $this->get(
            uri: self::ENDPOINT_URI . '?' . http_build_query(array_merge(['account_id' => $this->accountId], [
                'first_name' => $this->payments->first()->account->billingContact->first_name,
                'sort' => 'created_at',
                'per_page' => 2,
                'page' => 4,
            ])),
            headers: ['Api-Key' => config('auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
                'links' => [
                    'self' => [],
                ],
            ],
            'result' => [
                '*' => [
                    'payment_id',
                    'status',
                    'amount',
                    'created_at',
                ],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
    }

    public static function validInputProvider(): \Iterator
    {
        yield 'empty input' => [
            'input' => [],
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
            'expected' => [
                'current_page' => 4,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 2,
            ],
        ];
        yield 'has data, sort by created_at' => [
            'input' => [
                'per_page' => 2,
                'page' => 4,
                'sort' => 'created_at',
            ],
            'expected' => [
                'current_page' => 4,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 2,
            ],
        ];
        yield 'has data, sort by amount' => [
            'input' => [
                'per_page' => 2,
                'page' => 4,
                'sort' => 'amount',
            ],
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
            'expected' => [
                'current_page' => 6,
                'per_page' => 2,
                'total_pages' => 1,
                'total_results' => 0,
                'count_current_result' => 0,
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidInputProvider')]
    public function it_returns_400_bad_request_response_for_invalid_input(array $input): void
    {
        $accountExists = $this->createMock(AccountExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExists);

        $response = $this->get(
            uri: self::ENDPOINT_URI . '?' . http_build_query(array_merge(['account_id' => $this->accountId], $input)),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
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
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->accountId);
    }
}
