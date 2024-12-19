<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Repositories\Interface\AccountUpdaterAttemptRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\AccountUpdaterAttempt;
use App\Models\PaymentMethod;
use App\Services\FileReader\FileReader;
use App\Traits\LoadingAreaNames;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\Job as LaravelJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use League\Flysystem\UnableToReadFile;

class AccountUpdaterResultHandlerJob
{
    use LoadingAreaNames;

    private const string DIRECTORY_TO_READ_FROM = 'inbox';
    private const string DIRECTORY_TO_MOVE_INTO = 'archived';

    private string $fileName;
    private string|null $attemptUuid = null;
    private string $fileContent;
    private \Iterator $fileRows;
    private AccountUpdaterAttempt $attempt;
    private WriteApi $influxDbWriteApi;

    /**
     * @param FileReader $fileReader
     * @param AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param InfluxClient $influxClient
     * @param AreaRepository $areaRepository
     */
    public function __construct(
        private readonly FileReader $fileReader,
        private readonly AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly InfluxClient $influxClient,
        private readonly AreaRepository $areaRepository,
    ) {
        $this->influxDbWriteApi = $this->influxClient->createWriteApi();
    }

    /**
     * @param LaravelJob $job
     * @param array|null $data
     */
    public function handle(LaravelJob $job, array|null $data): void
    {
        Log::info(message: __('messages.tokenex.account_updater.got_message_in_queue'), context: ['parsed_message' => $data]);

        $this->validateAndParseMessage(data: $data);
        $this->loadFile();
        $this->parseFile();
        $this->processFile();
    }

    private function validateAndParseMessage(array|null $data): void
    {
        if (is_null($data)) {
            throw new \RuntimeException(message: __('messages.tokenex.account_updater.non_valid_json'));
        }

        if (!isset($data['FileName'])) {
            throw new \RuntimeException(message: __('messages.tokenex.account_updater.missing_file_name'));
        }

        $this->fileName = $data['FileName'];

        $fileNamePattern = '/payment-methods-([a-fA-F0-9\-]+)\.csv/';
        if (preg_match(pattern: $fileNamePattern, subject: $this->fileName, matches: $fileNameMatches)) {
            $this->attemptUuid = $fileNameMatches[1];
        }

        Log::info(message: __('messages.tokenex.account_updater.message_parsed'), context: [
            'file_name' => $this->fileName,
            'record_count' => $data['RecordCount'] ?? null,
            'error_count' => $data['ErrorCount'] ?? null,
        ]);
    }

    private function loadFile(): void
    {
        try {
            $this->fileContent = Storage::disk(name: 's3')->get(sprintf('%s/%s', self::DIRECTORY_TO_READ_FROM, $this->fileName));
        } catch (UnableToReadFile $exception) {
            Log::error(message: 'File was not found', context: [
                'error_message' => $exception->getMessage(),
                's3_bucket' => config(key: 'filesystems.disks.s3.bucket'),
                's3_directory' => self::DIRECTORY_TO_READ_FROM,
                'file_name' => $this->fileName,
            ]);

            throw new \RuntimeException(message: __('messages.tokenex.account_updater.file_not_found', ['message' => $exception->getMessage()]));
        }
    }

    private function parseFile(): void
    {
        $this->fileRows = $this->fileReader->fromString(content: $this->fileContent);

        Log::info(message: __('messages.tokenex.account_updater.file_parsed'), context: [
            'file_name' => $this->fileName,
            'rows_count' => iterator_count($this->fileRows)
        ]);
    }

    private function processFile(): void
    {
        $this->loadAttempt();
        $this->loadAreaNames();

        foreach ($this->fileRows as $fileRow) {
            $this->processFileRow(row: $fileRow);
        }

        $this->moveFile();

        Log::info(message: __('messages.tokenex.account_updater.file_processed_and_moved', ['directory' => self::DIRECTORY_TO_MOVE_INTO]));
    }

    /**
     * it loads account updater attempt by UUID (if it is in the file name), or trying to find unprocessed
     * account updater attempt by matching the first row of result file where the following fields are matching
     * in relation table:
     * - sequence number
     * - original token
     * - original expiration year
     * - original expiration month
     */
    private function loadAttempt(): void
    {
        if (!is_null($this->attemptUuid)) {
            $attempt = $this->accountUpdaterAttemptRepository->find(uuid: $this->attemptUuid);

            if (is_null($attempt)) {
                throw new \RuntimeException(message: __('messages.tokenex.account_updater.attempt_not_found_by_uuid'));
            }

            $this->attempt = $attempt;
            return;
        }

        $this->fileRows->rewind();
        $firstFileRow = $this->fileRows->current();

        [$token, $expirationDate, $sequenceNumber] = $firstFileRow;
        $expiration = $this->extractMonthAndYearFromExpirationDateString(expirationDate: $expirationDate);

        $attempt = $this->accountUpdaterAttemptRepository->firstWhereHasRelation(
            relation: 'methods',
            callback: static fn ($query) => $query->where([
                'sequence_number' => (int)$sequenceNumber,
                'original_token' => $token,
                'original_expiration_month' => $expiration['month'],
                'original_expiration_year' => $expiration['year'],
                'status' => null,
            ])
        );

        if (is_null($attempt)) {
            throw new \RuntimeException(message: __('messages.tokenex.account_updater.attempt_not_found_by_file_content'));
        }

        $this->attempt = $attempt;
    }

    /**
     * @param array $row expected file row format:
     *  - Token
     *  - Exp. Date (MMYY)
     *  - Sequence Number*
     *  - Updated Token
     *  - Updated Exp. Date (MMYY)
     *  - Response Message
     */
    private function processFileRow(array $row): void
    {
        [$token, $expirationDate, $sequenceNumber, $updatedToken, $updatedExpirationDate, $responseMessage] = $row;

        $expiration = $this->extractMonthAndYearFromExpirationDateString(expirationDate: $expirationDate);
        $updatedExpiration = $this->extractMonthAndYearFromExpirationDateString(expirationDate: $updatedExpirationDate);

        $paymentMethod = $this->paymentMethodRepository->findByAttemptRelation(
            attempt: $this->attempt,
            relationAttributes: [
                'sequence_number' => (int)$sequenceNumber,
                'original_token' => $token,
                'original_expiration_month' => $expiration['month'],
                'original_expiration_year' => $expiration['year'],
            ]
        );

        if (is_null($paymentMethod)) {
            Log::warning(
                message: __('messages.tokenex.account_updater.payment_method_not_found', ['number' => (int)$sequenceNumber]),
                context: ['row' => $row]
            );
            return;
        }

        // update attempt relation record
        $this->accountUpdaterAttemptRepository->updateExistingPivot(
            relation: $this->attempt->methods(),
            id: $paymentMethod,
            attributes: [
                'updated_token' => $updatedToken ?: null,
                'updated_expiration_month' => $updatedExpiration['month'],
                'updated_expiration_year' => $updatedExpiration['year'],
                'status' => $responseMessage ?: null,
            ]
        );

        // update payment method
        $this->paymentMethodRepository->update(paymentMethod: $paymentMethod, attributes: array_filter([
            'cc_token' => $updatedToken ?: null,
            'cc_expiration_month' => $updatedExpiration['month'],
            'cc_expiration_year' => $updatedExpiration['year'],
            'updated_by' => null, // TODO: add urn [COR-1203]
        ]));

        // record metrics
        $this->recordMetricsForPaymentMethodAttempt(paymentMethod: $paymentMethod, status: $responseMessage ?: null);
    }

    /** @return array<int|null> */
    private function extractMonthAndYearFromExpirationDateString(string|null $expirationDate): array
    {
        if (empty($expirationDate)) {
            return ['month' => null, 'year' => null];
        }

        $month = substr(string: $expirationDate, offset: 0, length: 2);
        $year = substr(string: $expirationDate, offset: 2, length: 2);

        return [
            'month' => (int)$month,
            'year' => (int)(\DateTime::createFromFormat('y', $year))->format(format: 'Y'),
        ];
    }

    private function recordMetricsForPaymentMethodAttempt(PaymentMethod $paymentMethod, string|null $status): void
    {
        $point = new Point(name: 'tokenex_account_updater_attempt_results');

        $point->addField('count', 1)
            ->addField('method_id', $paymentMethod->id)
            ->addField('customer_id', $paymentMethod->account->external_ref_id)
            ->addField('account_id', $paymentMethod->account->id)
            ->addField('area_id', $paymentMethod->account->area_id)
            ->addField('attempt_id', $this->attempt->id)
            ->addTag('status', $status ?? 'Undefined Status')
            ->addTag('area', $this->areaNames[$paymentMethod->account->area_id] ?? __('messages.influx.tags.undefined_office'))
            ->time(Carbon::now()->getTimestampMs());

        $this->influxDbWriteApi->write($point);
    }

    private function moveFile(): void
    {
        Storage::disk(name: 's3')->move(
            from: sprintf('%s/%s', self::DIRECTORY_TO_READ_FROM, $this->fileName),
            to: sprintf('%s/%s', self::DIRECTORY_TO_MOVE_INTO, $this->fileName)
        );
    }

    public function __destruct()
    {
        $this->influxDbWriteApi->close();
    }
}
