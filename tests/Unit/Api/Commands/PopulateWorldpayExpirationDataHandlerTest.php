<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\PopulateWorldpayExpirationDataHandler;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Jobs\WorldpayPaymentMethodUpdateExpirationDataJob;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Tests\Unit\UnitTestCase;

class PopulateWorldpayExpirationDataHandlerTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    #[DataProvider('paymentMethodsCountProvider')]
    public function it_dispatches_update_expiration_data_jobs_as_expected(int $paymentMethodsCount): void
    {
        /** @var Collection<int, PaymentMethod> $paymentMethods */
        $paymentMethods = PaymentMethod::factory()->count($paymentMethodsCount)->withoutRelationships()->make();
        $paymentMethodRepository = $this->mockPaymentMethodRepositoryWillReturnPaymentMethods($paymentMethods);

        Log::shouldReceive('info')->once()->with(__('messages.worldpay.populate_expiration_data.start'));
        Log::shouldReceive('info')->once()->with(__('messages.tokenex.account_updater.payment_methods_loaded'), ['total_count' => $paymentMethodsCount]);
        Log::shouldReceive('info')->once()->with(__('messages.worldpay.populate_expiration_data.end'));

        $handler = new PopulateWorldpayExpirationDataHandler(paymentMethodRepository: $paymentMethodRepository);
        $handler->handle();

        Queue::assertPushed(WorldpayPaymentMethodUpdateExpirationDataJob::class, $paymentMethodsCount);
    }

    public static function paymentMethodsCountProvider(): iterable
    {
        yield 'no payment methods' => [0];
        yield 'one payment method' => [1];
        yield 'multiple payment methods' => [10];
        yield 'multiple pages of payment methods' => [101];
    }

    /**
     * @param Collection<int, PaymentMethod> $paymentMethods
     *
     * @throws Exception
     */
    private function mockPaymentMethodRepositoryWillReturnPaymentMethods(Collection $paymentMethods): PaymentMethodRepository
    {
        $filter = [
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'type' => PaymentTypeEnum::CC->name,
            'has_cc_token' => true,
            'has_cc_expiration_data' => false,
            'per_page' => 100,
            'page' => 1,
        ];
        $columns = ['id', 'cc_token', 'account_id'];
        $withSoftDeleted = false;
        $relationsFilter = [];
        $withRelations = ['account' => ['area:id,external_ref_id']];

        $paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);

        // without pagination
        if (count($paymentMethods) <= 100) {
            $paymentMethodRepository->method('filter')
                ->with($filter, $columns, $withSoftDeleted, $relationsFilter, $withRelations)
                ->willReturn(
                    new LengthAwarePaginator(
                        items: $paymentMethods,
                        total: count($paymentMethods),
                        perPage: 100,
                        currentPage: 1,
                        options: []
                    )
                );

            return $paymentMethodRepository;
        }

        // with pagination
        $paginators = [];

        foreach ($paymentMethods->chunk(100) as $page => $chunk) {
            $paginators[] = new LengthAwarePaginator(
                items: $chunk,
                total: count($paymentMethods),
                perPage: 100,
                currentPage: $page + 1,
                options: []
            );
        }

        $paymentMethodRepository
            ->expects($this->exactly(count: (int)($paymentMethods->count() / 100) + 1))
            ->method(constraint: 'filter')
            ->willReturnCallback(static fn ($filters) => $paginators[$filters['page'] - 1]);

        return $paymentMethodRepository;
    }
}
