<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Repositories\Interface\AccountUpdaterAttemptRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Helpers\DateTimeHelper;
use App\Models\AccountUpdaterAttempt;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Services\Export\ExportService;
use App\Traits\LoadingAreaNames;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

class UpdateTokenexPaymentAccountsHandler
{
    use LoadingAreaNames;

    private const int FILE_SIZE_LIMIT_IN_BYTES = 2 * 1024 * 1024 * 1024;  // 2GB in bytes
    private const int PAGINATOR_PER_PAGE_SIZE = 100;

    /** @var Collection<int, PaymentMethod> */
    private Collection $paymentMethods;
    private AccountUpdaterAttempt $attempt;
    private array $fileData;
    private WriteApi $influxDbWriteApi;

    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param ExportService $exportService
     * @param AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository
     * @param InfluxClient $influxClient
     * @param AreaRepository $areaRepository
     */
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly ExportService $exportService,
        private readonly AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository,
        private readonly InfluxClient $influxClient,
        private readonly AreaRepository $areaRepository
    ) {
        $this->paymentMethods = collect();
        $this->influxDbWriteApi = $this->influxClient->createWriteApi();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        Log::info(message: __('messages.tokenex.account_updater.start'));

        $this->createAccountUpdaterAttempt();
        $this->retrievePaymentMethods();
        $this->buildFileDataAndRecordMetrics();
        $this->uploadFile();

        Log::info(message: __('messages.tokenex.account_updater.completed'));
    }

    private function createAccountUpdaterAttempt(): void
    {
        $this->attempt = $this->accountUpdaterAttemptRepository->create(attributes: [
            'requested_at' => now(),
        ]);
    }

    /**
     * retrieves Tokenex Payment methods, only Credit Cards, that expires in the next month
     *
     * @return void
     */
    private function retrievePaymentMethods(): void
    {
        do {
            $paginator = $this->paymentMethodRepository->filter(
                filter: [
                    'gateway_ids' => PaymentGatewayEnum::tokenexGateways(),
                    'type_ids' => PaymentTypeEnum::creditCardTypes(),
                    'cc_expire_from_date' => now()->addMonth()->startOfMonth()->format(format: DateTimeHelper::GENERAL_DATE_FORMAT),
                    'cc_expire_to_date' => now()->addMonths(value: 2)->startOfMonth()->format(format: DateTimeHelper::GENERAL_DATE_FORMAT),
                    'per_page' => self::PAGINATOR_PER_PAGE_SIZE,
                    'page' => $page ?? 1,
                ],
                columns: [
                    'id',
                    'cc_token',
                    'cc_expiration_month',
                    'cc_expiration_year',
                    'account_id', // for relation
                ],
                relationsFilter: [
                    'accountUpdaterAttempts' => false, // do not include payment methods that has a relation with attempt
                ],
                withRelations: ['account:id,area_id,external_ref_id']
            );
            $page = $paginator->currentPage() + 1;
            $this->paymentMethods = $this->paymentMethods->merge(items: $paginator->collect());
        } while ($paginator->hasMorePages());

        Log::info(message: __('messages.tokenex.account_updater.payment_methods_loaded'), context: [
            'total_count' => $this->paymentMethods->count(),
            'attempt_id' => $this->attempt->id,
        ]);
    }

    /**
     * It adds relation between payment method at updater attempt, records metrics, and builds an array that explicitly
     * complies with Tokenex requirements
     */
    private function buildFileDataAndRecordMetrics(): void
    {
        $this->loadAreaNames();

        $this->fileData = [];
        $sequenceNumber = 1;

        foreach ($this->paymentMethods as $paymentMethod) {
            $this->fileData[] = $this->buildFileRow(paymentMethod: $paymentMethod, sequenceNumber: $sequenceNumber);
            $this->createAttemptToMethodRelation(paymentMethod: $paymentMethod, sequenceNumber: $sequenceNumber);
            $this->recordMetricsForPaymentMethodAttempt(paymentMethod: $paymentMethod);

            $sequenceNumber++;
        }
    }

    /** Expected by Tokenex Structure: Token|Exp. Date (MMYY)|Sequence Number* */
    private function buildFileRow(PaymentMethod $paymentMethod, int $sequenceNumber): array
    {
        return [
            'token' => $paymentMethod->cc_token,
            'expiration_date' => sprintf('%02d%02d', $paymentMethod->cc_expiration_month, $paymentMethod->cc_expiration_year % 100),
            'sequence_number' => $sequenceNumber,
        ];
    }

    private function createAttemptToMethodRelation(PaymentMethod $paymentMethod, int $sequenceNumber): void
    {
        $this->attempt->methods()->attach(id: $paymentMethod->id, attributes: [
            'original_token' => $paymentMethod->cc_token,
            'original_expiration_month' => $paymentMethod->cc_expiration_month,
            'original_expiration_year' => $paymentMethod->cc_expiration_year,
            'sequence_number' => $sequenceNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordMetricsForPaymentMethodAttempt(PaymentMethod $paymentMethod): void
    {
        $point = new Point(name: 'tokenex_account_updater_attempts');

        $point->addField('count', 1)
            ->addField('method_id', $paymentMethod->id)
            ->addField('customer_id', $paymentMethod->account->external_ref_id)
            ->addField('account_id', $paymentMethod->account->id)
            ->addField('area_id', $paymentMethod->account->area_id)
            ->addField('attempt_id', $this->attempt->id)
            ->addTag('area', $this->areaNames[$paymentMethod->account->area_id] ?? __('messages.influx.tags.undefined_office'))
            ->time(Carbon::now()->getTimestampMs());

        $this->influxDbWriteApi->write($point);
    }

    private function uploadFile(): void
    {
        $this->exportService->exportToS3(
            data: $this->fileData,
            fileName: 'outbox/payment-methods-' . $this->attempt->id . '.csv',
            sizeLimit: self::FILE_SIZE_LIMIT_IN_BYTES
        );
    }

    public function __destruct()
    {
        $this->influxDbWriteApi->close();
    }
}
