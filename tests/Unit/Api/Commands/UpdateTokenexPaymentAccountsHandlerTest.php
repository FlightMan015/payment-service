<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\UpdateTokenexPaymentAccountsHandler;
use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Repositories\Interface\AccountUpdaterAttemptRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use App\Services\Export\ExportService;
use Illuminate\Pagination\LengthAwarePaginator;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\WriteApi;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class UpdateTokenexPaymentAccountsHandlerTest extends UnitTestCase
{
    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&ExportService $exportService */
    private ExportService $exportService;
    /** @var MockObject&AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository */
    private AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository;
    /** @var MockObject&InfluxClient $influxClient */
    private InfluxClient $influxClient;
    /** @var MockObject&AreaRepository $areaRepository */
    private AreaRepository $areaRepository;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->exportService = $this->createMock(originalClassName: ExportService::class);
        $this->accountUpdaterAttemptRepository = $this->createMock(originalClassName: AccountUpdaterAttemptRepository::class);
        $this->influxClient = $this->createMock(originalClassName: InfluxClient::class);
        $this->areaRepository = $this->createMock(originalClassName: AreaRepository::class);

        parent::setUp();
    }

    #[Test]
    public function handle_method_retrieves_payment_methods_builds_data_and_exports_it_in_expected_format(): void
    {
        $paymentMethod1 = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['cc_token' => '4444XXXXYYYY0000', 'cc_expiration_month' => 9, 'cc_expiration_year' => 2022],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );
        $paymentMethod2 = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['cc_token' => '4111YYYYXXXX1114', 'cc_expiration_month' => 10, 'cc_expiration_year' => 2023],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );

        $expectedMethodsData = [
            ['token' => '4444XXXXYYYY0000', 'expiration_date' => '0922', 'sequence_number' => 1],
            ['token' => '4111YYYYXXXX1114', 'expiration_date' => '1023', 'sequence_number' => 2],
        ];

        $this->paymentMethodRepository
            ->expects($this->once())
            ->method(constraint: 'filter')
            ->willReturn(new LengthAwarePaginator(items: collect([$paymentMethod1, $paymentMethod2]), total: 2, perPage: 10));

        $this->exportService->expects($this->once())->method(constraint: 'exportToS3')->with($expectedMethodsData);

        $this->handle();
    }

    #[Test]
    public function handle_method_writes_metrics_as_expected(): void
    {
        $mockWriteApi = Mockery::mock(WriteApi::class);
        $this->influxClient->method('createWriteApi')->willReturn($mockWriteApi);

        $paymentMethod1 = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['cc_token' => '4444XXXXYYYY0000', 'cc_expiration_month' => 9, 'cc_expiration_year' => 2022],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );
        $paymentMethod2 = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['cc_token' => '4111YYYYXXXX1114', 'cc_expiration_month' => 10, 'cc_expiration_year' => 2023],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );

        $this->paymentMethodRepository
            ->expects($this->once())
            ->method(constraint: 'filter')
            ->willReturn(new LengthAwarePaginator(items: collect([$paymentMethod1, $paymentMethod2]), total: 2, perPage: 10));

        $mockWriteApi->expects('write')->times(2);
        $mockWriteApi->expects('close');

        $this->handle();
    }

    #[Test]
    public function handle_method_retrieves_payment_methods_with_pagination_builds_data_and_exports_it_in_expected_format(): void
    {

        $paymentMethod1 = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['cc_token' => '4444XXXXYYYY0000', 'cc_expiration_month' => 9, 'cc_expiration_year' => 2022],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );
        $paymentMethod2 = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['cc_token' => '4111YYYYXXXX1114', 'cc_expiration_month' => 10, 'cc_expiration_year' => 2023],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );
        $paymentMethod3 = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['cc_token' => '4321XXYYYYXX0987', 'cc_expiration_month' => 11, 'cc_expiration_year' => 2023],
            relationships: ['account' => Account::factory()->withoutRelationships()->make()]
        );

        $expectedMethodsData = [
            ['token' => '4444XXXXYYYY0000', 'expiration_date' => '0922', 'sequence_number' => 1],
            ['token' => '4111YYYYXXXX1114', 'expiration_date' => '1023', 'sequence_number' => 2],
            ['token' => '4321XXYYYYXX0987', 'expiration_date' => '1123', 'sequence_number' => 3],
        ];

        $this->paymentMethodRepository
            ->expects($this->exactly(count: 2))
            ->method(constraint: 'filter')
            ->willReturnOnConsecutiveCalls(
                new LengthAwarePaginator(items: collect([$paymentMethod1, $paymentMethod2]), total: 3, perPage: 2),
                new LengthAwarePaginator(items: collect([$paymentMethod3]), total: 3, perPage: 2, currentPage: 2)
            );

        $this->exportService->expects($this->once())->method(constraint: 'exportToS3')->with($expectedMethodsData);

        $this->handle();
    }

    private function handle(): void
    {
        (new UpdateTokenexPaymentAccountsHandler(
            paymentMethodRepository: $this->paymentMethodRepository,
            exportService: $this->exportService,
            accountUpdaterAttemptRepository: $this->accountUpdaterAttemptRepository,
            influxClient: $this->influxClient,
            areaRepository: $this->areaRepository
        ))->handle();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->exportService, $this->paymentMethodRepository, $this->accountUpdaterAttemptRepository, $this->influxClient);
    }
}
