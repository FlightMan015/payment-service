<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Repositories\Interface\AccountUpdaterAttemptRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Helpers\JsonDecoder;
use App\Jobs\AccountUpdaterResultHandlerJob;
use App\Models\AccountUpdaterAttempt;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use App\Services\FileReader\FileReader;
use Illuminate\Contracts\Queue\Job as LaravelJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\WriteApi;
use League\Flysystem\UnableToReadFile;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class AccountUpdaterResultHandlerJobTest extends UnitTestCase
{
    private const string FILE_NAME = 'payment-methods-dbc9c6d0-d7fb-4b24-88e0-c42d9a120fd7.csv';
    private const string FILE_NAME_WITHOUT_UUID = 'attempt_result.csv';
    private const string VALID_INPUT_MESSAGE = <<<JSON
{
    "FileName": "%s",
    "RecordCount": 2,
    "ErrorCount": 0
}
JSON;
    private const string INVALID_INPUT_MESSAGE = '{"key":"Invalid message"}';

    /** @var MockObject&LaravelJob $laravelJobMock */
    private LaravelJob $laravelJobMock;

    /** @var MockObject&FileReader $fileReaderMock */
    private FileReader $fileReaderMock;
    /** @var MockObject&AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository */
    private AccountUpdaterAttemptRepository $accountUpdaterAttemptRepository;
    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&InfluxClient $influxClient */
    private InfluxClient $influxClient;
    /** @var MockObject&AreaRepository $areaRepository */
    private AreaRepository $areaRepository;

    protected function setUp(): void
    {
        $this->laravelJobMock = $this->createMock(originalClassName: LaravelJob::class);
        $this->fileReaderMock = $this->createMock(originalClassName: FileReader::class);
        $this->accountUpdaterAttemptRepository = $this->createMock(originalClassName: AccountUpdaterAttemptRepository::class);
        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->influxClient = $this->createMock(originalClassName: InfluxClient::class);
        $this->areaRepository = $this->createMock(originalClassName: AreaRepository::class);

        parent::setUp();
    }

    #[Test]
    public function handle_method_parsing_message_and_processing_file_with_uuid_in_name_as_expected(): void
    {
        $message = sprintf(self::VALID_INPUT_MESSAGE, self::FILE_NAME);
        $this->assertGotMessageInTheQueueLog(message: $message);
        $this->assertMessageParsedLog(fileName: self::FILE_NAME);

        Storage::shouldReceive('disk->get')->once()->andReturn('444455U7VYWE0170,1225,01,,,Card Record Not Found');

        $iterator = new \ArrayIterator(array:[
            [
                '444455U7VYWE0170',
                '1225',
                '01',
                '',
                '',
                'Card Record Not Found'
            ],
        ]);
        $this->fileReaderMock->method('fromString')->willReturn($iterator);

        $this->assertFileParsedLog(fileName: self::FILE_NAME, rowsCount: iterator_count($iterator));

        $this->accountUpdaterAttemptRepository->method('find')
            ->willReturn(AccountUpdaterAttempt::factory()->make());
        $this->paymentMethodRepository->method('findByAttemptRelation')
            ->willReturn(PaymentMethod::factory()->cc()->makeWithRelationships(relationships: ['account' => Account::factory()->withoutRelationships()->make()]));

        Storage::shouldReceive('disk->move')->once();

        $this->assertFileProcessedLog();

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode($message));
    }

    #[Test]
    public function handle_method_parsing_message_and_processing_file_without_uuid_in_name_as_expected(): void
    {
        $message = sprintf(self::VALID_INPUT_MESSAGE, self::FILE_NAME_WITHOUT_UUID);
        $this->assertGotMessageInTheQueueLog(message: $message);
        $this->assertMessageParsedLog(fileName: self::FILE_NAME_WITHOUT_UUID);

        Storage::shouldReceive('disk->get')->once()->andReturn('444455F67P0X0014,1225,02,,,Valid account; no update');

        $iterator = new \ArrayIterator(array:[
            [
                '444455F67P0X0014',
                '1225',
                '01',
                '',
                '',
                'Valid account; no update'
            ],
        ]);
        $this->fileReaderMock->method('fromString')->willReturn($iterator);

        $this->assertFileParsedLog(fileName: self::FILE_NAME_WITHOUT_UUID, rowsCount: iterator_count($iterator));

        $this->accountUpdaterAttemptRepository->method('firstWhereHasRelation')->willReturn(AccountUpdaterAttempt::factory()->make());
        $this->paymentMethodRepository->method('findByAttemptRelation')
            ->willReturn(PaymentMethod::factory()->cc()->makeWithRelationships(relationships: ['account' => Account::factory()->withoutRelationships()->make()]));

        Storage::shouldReceive('disk->move')->once();

        $this->assertFileProcessedLog();

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode($message));
    }

    #[Test]
    public function handle_method_writes_metrics_as_expected(): void
    {
        $mockWriteApi = Mockery::mock(WriteApi::class);
        $this->influxClient->method('createWriteApi')->willReturn($mockWriteApi);

        $message = sprintf(self::VALID_INPUT_MESSAGE, self::FILE_NAME);
        $this->assertGotMessageInTheQueueLog(message: $message);
        $this->assertMessageParsedLog(fileName: self::FILE_NAME);

        Storage::shouldReceive('disk->get')->once()->andReturn('444455U7VYWE0170,1225,01,,,Card Record Not Found');

        $iterator = new \ArrayIterator(array:[
            [
                '444455U7VYWE0170',
                '1225',
                '01',
                '',
                '',
                'Card Record Not Found'
            ],
        ]);
        $this->fileReaderMock->method('fromString')->willReturn($iterator);

        $this->assertFileParsedLog(fileName: self::FILE_NAME, rowsCount: iterator_count($iterator));

        $this->accountUpdaterAttemptRepository->method('find')->willReturn(AccountUpdaterAttempt::factory()->make());
        $this->paymentMethodRepository->method('findByAttemptRelation')
            ->willReturn(PaymentMethod::factory()->cc()->makeWithRelationships(relationships: ['account' => Account::factory()->withoutRelationships()->make()]));

        Storage::shouldReceive('disk->move')->once();

        $this->assertFileProcessedLog();

        $mockWriteApi->expects('write')->times(1);
        $mockWriteApi->expects('close');

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode($message));
    }

    #[Test]
    public function handle_method_processing_file_as_expected_and_adding_warning_log_when_cannot_find_payment_method(): void
    {
        $message = sprintf(self::VALID_INPUT_MESSAGE, self::FILE_NAME);
        $this->assertGotMessageInTheQueueLog(message: $message);
        $this->assertMessageParsedLog(fileName: self::FILE_NAME);

        Storage::shouldReceive('disk->get')->once()->andReturn(<<<CSV
444455U7VYWE0170,1225,01,,,Card Record Not Found
444455F67P0X0014,1225,02,,,Valid account; no update
CSV);

        $row1 = [
            '444455U7VYWE0170',
            '1225',
            '01',
            '',
            '',
            'Card Record Not Found'
        ];

        $row2 = [
            '444455F67P0X0014',
            '1225',
            '02',
            '',
            '',
            'Valid account; no update'
        ];

        $iterator = new \ArrayIterator(array:[$row1, $row2]);
        $this->fileReaderMock->method('fromString')->willReturn($iterator);

        $this->assertFileParsedLog(fileName: self::FILE_NAME, rowsCount: iterator_count($iterator));

        $this->accountUpdaterAttemptRepository->method('find')->willReturn(AccountUpdaterAttempt::factory()->make());
        $this->paymentMethodRepository->method('findByAttemptRelation')->willReturnOnConsecutiveCalls(
            PaymentMethod::factory()->cc()->makeWithRelationships(relationships: ['account' => Account::factory()->withoutRelationships()->make()]), // first row found
            null // second row payment method not found
        );

        Log::shouldReceive('warning')->with(
            __('messages.tokenex.account_updater.payment_method_not_found', ['number' => (int)$row2[2]]),
            ['row' => $row2]
        )->once();

        Storage::shouldReceive('disk->move')->once();

        $this->assertFileProcessedLog();

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode($message));
    }

    #[Test]
    public function handle_method_throws_exception_if_got_invalid_input(): void
    {
        $this->assertGotMessageInTheQueueLog(message: null);

        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: __('messages.tokenex.account_updater.non_valid_json'));

        $this->job()->handle(job: $this->laravelJobMock, data: null);
    }

    #[Test]
    public function handle_method_throws_exception_if_got_invalid_input_message(): void
    {
        $this->assertGotMessageInTheQueueLog(message: self::INVALID_INPUT_MESSAGE);

        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: __('messages.tokenex.account_updater.missing_file_name'));

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode(self::INVALID_INPUT_MESSAGE));
    }

    #[Test]
    public function handle_method_add_error_log_and_throws_exception_if_file_was_not_found_in_s3(): void
    {
        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: 'File was not found: Unable to read file');

        $message = sprintf(self::VALID_INPUT_MESSAGE, self::FILE_NAME);
        $this->assertGotMessageInTheQueueLog(message: $message);
        $this->assertMessageParsedLog(fileName: self::FILE_NAME);

        Log::shouldReceive('error')->with(
            'File was not found',
            [
                'error_message' => 'Unable to read file',
                's3_bucket' => config(key: 'filesystems.disks.s3.bucket'),
                's3_directory' => 'inbox',
                'file_name' => self::FILE_NAME,
            ]
        )->once();

        Storage::shouldReceive('disk->get')->once()->andThrow(new UnableToReadFile(message: 'Unable to read file'));

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode($message));
    }

    #[Test]
    public function handle_method_throws_exception_if_attempt_cannot_be_found_by_uuid_in_the_filename(): void
    {
        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: __('messages.tokenex.account_updater.attempt_not_found_by_uuid'));

        $message = sprintf(self::VALID_INPUT_MESSAGE, self::FILE_NAME);
        $this->assertGotMessageInTheQueueLog(message: $message);
        $this->assertMessageParsedLog(fileName: self::FILE_NAME);

        Storage::shouldReceive('disk->get')->once()->andReturn('444455U7VYWE0170,1225,01,,,Card Record Not Found');

        $iterator = new \ArrayIterator(array:[
            [
                '444455U7VYWE0170',
                '1225',
                '01',
                '',
                '',
                'Card Record Not Found'
            ],
        ]);
        $this->fileReaderMock->method('fromString')->willReturn($iterator);

        $this->assertFileParsedLog(fileName: self::FILE_NAME, rowsCount: iterator_count($iterator));

        $this->accountUpdaterAttemptRepository->method('find')->willReturn(null);

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode($message));
    }

    #[Test]
    public function handle_method_throws_exception_if_attempt_cannot_be_found_by_first_row_in_file_when_file_does_not_include_uuid(): void
    {
        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: __('messages.tokenex.account_updater.attempt_not_found_by_file_content'));

        $message = sprintf(self::VALID_INPUT_MESSAGE, self::FILE_NAME_WITHOUT_UUID);
        $this->assertGotMessageInTheQueueLog(message: $message);
        $this->assertMessageParsedLog(fileName: self::FILE_NAME_WITHOUT_UUID);

        Storage::shouldReceive('disk->get')->once()->andReturn('444455F67P0X0014,1225,02,,,Valid account; no update');

        $iterator = new \ArrayIterator(array:[
            [
                '444455F67P0X0014',
                '1225',
                '01',
                '',
                '',
                'Valid account; no update'
            ],
        ]);
        $this->fileReaderMock->method('fromString')->willReturn($iterator);

        $this->assertFileParsedLog(fileName: self::FILE_NAME_WITHOUT_UUID, rowsCount: iterator_count($iterator));

        $this->accountUpdaterAttemptRepository->method('firstWhereHasRelation')->willReturn(null);

        $this->job()->handle(job: $this->laravelJobMock, data: JsonDecoder::decode($message));
    }

    private function assertGotMessageInTheQueueLog(mixed $message): void
    {
        Log::shouldReceive('info')->with(
            __('messages.tokenex.account_updater.got_message_in_queue'),
            ['parsed_message' => is_string($message) && JsonDecoder::isValidJsonString($message) ? JsonDecoder::decode($message) : null]
        )->once();
    }

    private function assertMessageParsedLog(string $fileName): void
    {
        Log::shouldReceive('info')->with(
            __('messages.tokenex.account_updater.message_parsed'),
            ['file_name' => $fileName, 'record_count' => 2, 'error_count' => 0]
        )->once();
    }

    private function assertFileParsedLog(string $fileName, int $rowsCount): void
    {
        Log::shouldReceive('info')->with(
            'File was successfully parsed',
            ['file_name' => $fileName, 'rows_count' => $rowsCount]
        )->once();
    }

    private function assertFileProcessedLog(): void
    {
        Log::shouldReceive('info')->with(__('messages.tokenex.account_updater.file_processed_and_moved', ['directory' => 'archived']))->once();
    }

    private function job(): AccountUpdaterResultHandlerJob
    {
        return new AccountUpdaterResultHandlerJob(
            fileReader: $this->fileReaderMock,
            accountUpdaterAttemptRepository: $this->accountUpdaterAttemptRepository,
            paymentMethodRepository: $this->paymentMethodRepository,
            influxClient: $this->influxClient,
            areaRepository: $this->areaRepository
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->job,
            $this->laravelJobMock,
            $this->fileReaderMock,
            $this->accountUpdaterAttemptRepository,
            $this->paymentMethodRepository,
            $this->influxClient
        );
    }
}
