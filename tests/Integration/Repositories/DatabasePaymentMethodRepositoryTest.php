<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\DatabasePaymentMethodRepository;
use App\Models\AccountUpdaterAttempt;
use App\Models\CRM\Customer\Account;
use App\Models\Gateway;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\RepositoryFilterMethodTestTrait;
use Tests\TestCase;

class DatabasePaymentMethodRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    use RepositoryFilterMethodTestTrait;

    private DatabasePaymentMethodRepository $repository;
    private PaymentMethod|null $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app()->make(DatabasePaymentMethodRepository::class);
        $this->paymentMethod = $this->createCreditCardPaymentMethodInDatabase();
    }

    #[Test]
    public function find_method_throws_not_found_exception_for_not_existing_result(): void
    {
        $paymentMethodId = Str::uuid()->toString();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment_method.not_found', ['id' => $paymentMethodId]));
        $this->repository->find(paymentMethodId: $paymentMethodId);
    }

    #[Test]
    public function find_method_throws_not_found_with_deleted_payment_method(): void
    {
        $this->paymentMethod->delete();
        $this->paymentMethod->save();
        $paymentMethodId = $this->paymentMethod->id;

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment_method.not_found', ['id' => $paymentMethodId]));
        $this->repository->find(paymentMethodId: $paymentMethodId);
    }

    #[Test]
    public function it_creates_payment_method_with_ach_account_number_encrypted_by_default(): void
    {
        $achAccountNumberRaw = '12345678';
        $paymentMethod = $this->createAchPaymentMethodInDatabase([
            'ach_account_number_encrypted' => $achAccountNumberRaw
        ]);
        $this->assertNotEmpty($paymentMethod->ach_account_number_encrypted);
        $this->assertNotEquals($achAccountNumberRaw, $paymentMethod->ach_account_number_encrypted);
    }

    #[Test]
    public function find_return_correct_payment(): void
    {
        $columns = [
            'id',
            'account_id',
            'pestroutes_created_at',
            'created_at',
            'payment_type_id',
            'last_four',
            'ach_routing_number',
            'ach_account_type',
            'cc_expiration_month',
            'cc_expiration_year',
        ];
        $actual = $this->repository->find(paymentMethodId: $this->paymentMethod->id, columns: $columns);

        $this->assertInstanceOf(PaymentMethod::class, $actual);
        $this->assertTrue(!array_diff($columns, array_keys($actual->toArray())));
    }

    #[Test]
    public function it_updates_all_payment_methods_of_account(): void
    {
        $account = Account::factory()->create();

        $paymentMethod1 = $this->createCreditCardPaymentMethodInDatabase(['account_id' => $account->id]);
        $paymentMethod2 = $this->createCreditCardPaymentMethodInDatabase(['account_id' => $account->id]);

        $this->repository->updateForAccount(
            accountId: $account->id,
            attributes: [
                'name_on_account' => 'updated string'
            ]
        );
        $paymentMethod1->refresh();
        $this->assertSame('updated string', $paymentMethod1->name_on_account);
        $paymentMethod2->refresh();
        $this->assertSame('updated string', $paymentMethod2->name_on_account);
    }

    #[Test]
    public function it_filters_valid_payment_methods_as_expected(): void
    {
        PaymentMethod::query()->delete(); // delete all payment method from DB

        // create 3 valid CC payment methods
        $validCcPaymentMethodsCount = 3;
        PaymentMethod::factory()->count(count: $validCcPaymentMethodsCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        // create 2 ACH payment method (always valid)
        $achPaymentMethodsCount = 2;
        PaymentMethod::factory()->count(count: $achPaymentMethodsCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'cc_expiration_month' => null,
            'cc_expiration_year' => null,
        ]);

        // create 2 invalid payment methods
        $invalidCcPaymentMethodsCount = 2;
        PaymentMethod::factory()->count(count: $invalidCcPaymentMethodsCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'last year')),
        ]);

        $columns = [
            'id',
            'account_id',
            'pestroutes_created_at',
            'created_at',
            'payment_type_id',
            'last_four',
            'ach_routing_number',
            'ach_account_type',
            'cc_expiration_month',
            'cc_expiration_year',
        ];
        $paymentMethods = $this->repository->filter(
            filter: ['is_valid' => true],
            columns: $columns,
        );

        $this->assertCount(expectedCount: $validCcPaymentMethodsCount + $achPaymentMethodsCount, haystack: $paymentMethods);
        $this->assertTrue(!array_diff($columns, array_keys($paymentMethods->items()[0]->toArray())));
    }

    #[Test]
    public function it_filters_invalid_payment_methods_as_expected(): void
    {
        PaymentMethod::query()->delete(); // delete all payment method from DB

        // create 4 invalid payment methods
        $invalidCcPaymentMethodsCount = 4;
        PaymentMethod::factory()->count(count: $invalidCcPaymentMethodsCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'last year')),
        ]);

        // create 10 valid CC payment methods
        $validCcPaymentMethodsCount = 10;
        PaymentMethod::factory()->count(count: $validCcPaymentMethodsCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        // create 7 ACH payment method (always valid)
        $achPaymentMethodsCount = 7;
        PaymentMethod::factory()->count(count: $achPaymentMethodsCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'cc_expiration_month' => null,
            'cc_expiration_year' => null,
        ]);

        $paymentMethods = $this->repository->filter(filter: ['is_valid' => false]);

        $this->assertCount(expectedCount: $invalidCcPaymentMethodsCount, haystack: $paymentMethods);
    }

    #[Test]
    public function it_filters_valid_payment_methods_for_specific_account_as_expected(): void
    {
        PaymentMethod::query()->delete(); // delete all payment method from DB

        $accountToSearch = Account::factory()->create();
        $accountToExclude = Account::factory()->create();

        // create 3 valid CC payment methods for account to search
        $validCcPaymentMethodsForTheFirstAccountCount = 3;
        PaymentMethod::factory()->for($accountToSearch)->count(count: $validCcPaymentMethodsForTheFirstAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        // create 2 ACH payment method (always valid)s for account to search
        $achPaymentMethodsForTheFirstAccountCount = 2;
        PaymentMethod::factory()->for($accountToSearch)->count(count: $achPaymentMethodsForTheFirstAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'cc_expiration_month' => null,
            'cc_expiration_year' => null,
        ]);

        // create 2 invalid payment methods for account to search
        $invalidCcPaymentMethodForTheFirstAccountCount = 2;
        PaymentMethod::factory()->for($accountToSearch)->count(count: $invalidCcPaymentMethodForTheFirstAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'last year')),
        ]);

        // create 4 valid CC payment methods for account to exclude
        $validCcPaymentMethodsForTheSecondAccountCount = 4;
        PaymentMethod::factory()->for($accountToExclude)->count(count: $validCcPaymentMethodsForTheSecondAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        // create 1 invalid payment method for account to exclude
        $invalidCcPaymentMethodsForTheSecondAccountCount = 1;
        PaymentMethod::factory()->for($accountToExclude)->count(count: $invalidCcPaymentMethodsForTheSecondAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'last year')),
        ]);

        $paymentMethods = $this->repository->filter(filter: ['is_valid' => true, 'account_id' => $accountToSearch->id]);

        $this->assertCount(
            expectedCount: $validCcPaymentMethodsForTheFirstAccountCount + $achPaymentMethodsForTheFirstAccountCount,
            haystack: $paymentMethods
        );
    }

    #[Test]
    public function it_filters_invalid_payment_methods_for_specific_account_as_expected(): void
    {
        PaymentMethod::query()->delete(); // delete all payment method from DB

        $accountToSearch = Account::factory()->create();
        $accountToExclude = Account::factory()->create();

        // create 4 invalid payment methods for account to search
        $invalidCcPaymentMethodsForTheFirstAccountCount = 4;
        PaymentMethod::factory()->for($accountToSearch)->count(count: $invalidCcPaymentMethodsForTheFirstAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'last year')),
        ]);

        // create 10 valid CC payment methods for account to search
        $validCcPaymentMethodsForTheFirstAccountCount = 10;
        PaymentMethod::factory()->for($accountToSearch)->count(count: $validCcPaymentMethodsForTheFirstAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        // create 7 ACH payment method (always valid) for account to search
        $achPaymentMethodsForTheFirstAccountCount = 7;
        PaymentMethod::factory()->for($accountToSearch)->count(count: $achPaymentMethodsForTheFirstAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'cc_expiration_month' => null,
            'cc_expiration_year' => null,
        ]);

        // create 4 invalid payment methods for account to exclude
        $invalidCcPaymentMethodsForTheSecondAccountCount = 4;
        PaymentMethod::factory()->for($accountToExclude)->count(count: $invalidCcPaymentMethodsForTheSecondAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'last year')),
        ]);

        // create 2 ACH payment method (always valid) for account to exclude
        $schPaymentMethodsForTheSecondAccountCount = 2;
        PaymentMethod::factory()->for($accountToExclude)->count(count: $schPaymentMethodsForTheSecondAccountCount)->create(attributes: [
            'payment_type_id' => PaymentTypeEnum::ACH->value,
            'cc_expiration_month' => null,
            'cc_expiration_year' => null,
        ]);

        $paymentMethods = $this->repository->filter(filter: ['is_valid' => false, 'account_id' => $accountToSearch->id]);

        $this->assertCount(expectedCount: $invalidCcPaymentMethodsForTheFirstAccountCount, haystack: $paymentMethods);
    }

    #[Test]
    public function it_filters_payment_methods_by_gateway_ids_as_expected(): void
    {
        $firstGateway = Gateway::factory()->create(['id' => Gateway::max(column: 'id') + 1]);
        $secondGateway = Gateway::factory()->create(['id' => Gateway::max(column: 'id') + 1]);

        PaymentMethod::factory()->count(count: 2)->create(attributes: ['payment_gateway_id' => $firstGateway->id]);
        PaymentMethod::factory()->count(count: 3)->create(attributes: ['payment_gateway_id' => $secondGateway->id]);

        $this->assertCount(
            expectedCount: 2,
            haystack: $this->repository->filter(filter: ['gateway_ids' => [$firstGateway->id]], withSoftDeleted: true)
        );
        $this->assertCount(
            expectedCount: 3,
            haystack: $this->repository->filter(filter: ['gateway_ids' => [$secondGateway->id]], withSoftDeleted: true)
        );
        $this->assertCount(
            expectedCount: 2 + 3,
            haystack: $this->repository->filter(filter: ['gateway_ids' => [$firstGateway->id, $secondGateway->id]], withSoftDeleted: true)
        );
    }

    #[Test]
    public function it_filters_payment_methods_by_type_ids_as_expected(): void
    {
        PaymentMethod::query()->delete();

        $firstType = PaymentType::inRandomOrder()->first();
        $secondType = PaymentType::where(column: 'id', operator: '!=', value: $firstType->id)->inRandomOrder()->first();

        PaymentMethod::factory()->count(count: 3)->create(attributes: ['payment_type_id' => $firstType->id]);
        PaymentMethod::factory()->count(count: 6)->create(attributes: ['payment_type_id' => $secondType->id]);

        $this->assertCount(
            expectedCount: 3,
            haystack: $this->repository->filter(filter: ['type_ids' => [$firstType->id]])
        );
        $this->assertCount(
            expectedCount: 6,
            haystack: $this->repository->filter(filter: ['type_ids' => [$secondType->id]])
        );
        $this->assertCount(
            expectedCount: 3 + 6,
            haystack: $this->repository->filter(filter: ['type_ids' => [$firstType->id, $secondType->id]])
        );
    }

    #[Test]
    public function it_filters_payment_methods_depending_on_relation(): void
    {
        PaymentMethod::query()->delete(); // delete all payment method from DB

        $paymentMethodsWithRelation = PaymentMethod::factory()->count(count: 3)->cc()->create();
        $accountUpdaterAttempt = AccountUpdaterAttempt::factory()->create();
        foreach ($paymentMethodsWithRelation as $paymentMethod) {
            $accountUpdaterAttempt->methods()->attach(id: $paymentMethod->id, attributes: [
                'original_token' => $paymentMethod->cc_token,
                'original_expiration_year' => $paymentMethod->cc_expiration_year,
                'original_expiration_month' => $paymentMethod->cc_expiration_month,
                'sequence_number' => random_int(min: 1, max: 10000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // without a relation
        PaymentMethod::factory()->count(count: 5)->cc()->create();

        $this->assertCount(
            expectedCount: 3,
            haystack: $this->repository->filter(relationsFilter: ['accountUpdaterAttempts' => true])
        );

        $this->assertCount(
            expectedCount: 5,
            haystack: $this->repository->filter(relationsFilter: ['accountUpdaterAttempts' => false])
        );
    }

    #[Test]
    public function it_filters_payment_methods_by_expiration_data_presence_as_expected(): void
    {
        PaymentMethod::query()->delete(); // delete all payment method from DB

        $paymentMethodsWithExpirationDataQuantity = 3;
        $paymentMethodsWithoutExpirationDataQuantity = 4;

        PaymentMethod::factory()->count(count: $paymentMethodsWithExpirationDataQuantity)->cc()->create();
        PaymentMethod::factory()->count(count: $paymentMethodsWithoutExpirationDataQuantity)->cc()->create(attributes: [
            'cc_expiration_month' => null,
            'cc_expiration_year' => null,
        ]);

        $this->assertCount(
            expectedCount: $paymentMethodsWithExpirationDataQuantity,
            haystack: $this->repository->filter(filter: ['has_cc_expiration_data' => true])
        );

        $this->assertCount(
            expectedCount: $paymentMethodsWithoutExpirationDataQuantity,
            haystack: $this->repository->filter(filter: ['has_cc_expiration_data' => false])
        );
    }

    #[Test]
    public function it_filters_payment_methods_by_cc_token_presence_as_expected(): void
    {
        PaymentMethod::query()->delete(); // delete all payment method from DB

        $paymentMethodsWithTokenQuantity = 3;
        $paymentMethodsWithoutTokenQuantity = 4;

        PaymentMethod::factory()->count(count: $paymentMethodsWithTokenQuantity)->cc()->create();
        PaymentMethod::factory()->count(count: $paymentMethodsWithoutTokenQuantity)->cc()->create(attributes: [
            'cc_token' => null,
        ]);

        $this->assertCount(
            expectedCount: $paymentMethodsWithTokenQuantity,
            haystack: $this->repository->filter(filter: ['has_cc_token' => true])
        );

        $this->assertCount(
            expectedCount: $paymentMethodsWithoutTokenQuantity,
            haystack: $this->repository->filter(filter: ['has_cc_token' => false])
        );
    }

    #[Test]
    public function find_by_external_ref_id_method_returns_correct_payment_method(): void
    {
        $actual = $this->repository->findByExternalRefId(externalRefId: $this->paymentMethod->external_ref_id);

        $this->assertSame($this->paymentMethod->id, $actual->id);
        $this->assertSame($this->paymentMethod->external_ref_id, $actual->external_ref_id);
    }

    #[Test]
    public function find_by_external_ref_id_method_throws_not_found_exception_for_not_existing_result(): void
    {
        $externalRefId = random_int(10000, 20000);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment_method.not_found_by_external_ref_id', ['id' => $externalRefId]));

        $this->repository->findByExternalRefId(externalRefId: $externalRefId);
    }

    #[Test]
    public function find_by_external_ref_id_method_throws_not_found_with_deleted_payment_method(): void
    {
        $this->paymentMethod->delete();
        $this->paymentMethod->save();
        $externalRefId = $this->paymentMethod->external_ref_id;

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment_method.not_found_by_external_ref_id', ['id' => $externalRefId]));
        $this->repository->findByExternalRefId(externalRefId: $externalRefId);
    }

    protected function getEntity(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    protected function getRepository(): mixed
    {
        return $this->repository;
    }

    private function createCreditCardPaymentMethodInDatabase(array $attributes = []): PaymentMethod
    {
        $paymentMethod = PaymentMethod::factory()->cc()->create($attributes);

        return $paymentMethod->with('type')->find($paymentMethod->id);
    }

    private function createAchPaymentMethodInDatabase(array $attributes = []): PaymentMethod
    {
        $paymentMethod = PaymentMethod::factory()->ach()->create($attributes);

        return $paymentMethod->with('type')->find($paymentMethod->id);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->paymentMethod);

        parent::tearDown();
    }
}
