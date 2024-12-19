<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Services\Export;

use App\Services\Export\CsvExportService;
use App\Services\FileGenerator\FileGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\AbstractCsv;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class CsvExportServiceTest extends UnitTestCase
{
    /** @var MockObject&FileGenerator $fileGenerator */
    private FileGenerator $fileGenerator;
    private CsvExportService $csvExportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileGenerator = $this->createMock(originalClassName: FileGenerator::class);
        $this->csvExportService = new CsvExportService(fileGenerator: $this->fileGenerator);
    }

    #[Test]
    public function it_exports_given_data_to_s3_as_expected_without_size_limit(): void
    {
        $csvString = <<<CSV
token1,0123,1
token2,0123,2
CSV;

        $csvFileMock = $this->createMock(AbstractCsv::class);
        $csvFileMock->method('toString')->willReturn($csvString);
        $this->fileGenerator->method('generateFile')->willReturn($csvFileMock);

        Storage::fake(disk: 's3');

        $fileName = 'test.csv';
        $result = $this->csvExportService->exportToS3(
            data: [
                ['token' => 'token1', 'expiration_date' => '0123', 'sequence' => 1],
                ['token' => 'token2', 'expiration_date' => '0123', 'sequence' => 2],
            ],
            fileName: $fileName,
            sizeLimit: null
        );

        Storage::disk('s3')->assertExists($fileName);
        $this->assertSame(expected: $csvString, actual: Storage::disk(name: 's3')->get(path: $fileName));
        $this->assertInfoLogAdded(fileName: $fileName, content: $csvString);

        $this->assertTrue(condition: $result);
    }

    #[Test]
    public function it_exports_given_data_to_s3_as_expected_with_limit_that_is_more_than_file_size(): void
    {
        $csvString = <<<CSV
token1,0123,1
token2,0123,2
token3,0123,3
CSV;

        $csvFileMock = $this->createMock(AbstractCsv::class);
        $csvFileMock->method('toString')->willReturn($csvString);
        $this->fileGenerator->method('generateFile')->willReturn($csvFileMock);

        Storage::fake(disk: 's3');

        $fileName = 'test.csv';
        $result = $this->csvExportService->exportToS3(
            data: [
                ['token' => 'token1', 'expiration_date' => '0123', 'sequence' => 1],
                ['token' => 'token2', 'expiration_date' => '0123', 'sequence' => 2],
                ['token' => 'token3', 'expiration_date' => '0123', 'sequence' => 3],
            ],
            fileName: $fileName,
            sizeLimit: 1024
        );

        Storage::disk('s3')->assertExists($fileName);
        $this->assertSame(expected: $csvString, actual: Storage::disk(name: 's3')->get(path: $fileName));
        $this->assertInfoLogAdded(fileName: $fileName, content: $csvString);

        $this->assertTrue(condition: $result);
    }

    #[Test]
    public function it_exports_given_data_to_s3_as_expected_and_split_file_into_chunks_with_the_correct_file_names_and_content(): void
    {
        $csvContent = <<<CSV
4444XXXXYYYY0001,0123,1
4444XXXXYYYY0002,0123,2
4444XXXXYYYY0003,0123,3
4444XXXXYYYY0004,0123,4
4444XXXXYYYY0005,0123,5
4444XXXXYYYY0006,0123,6
4444XXXXYYYY0007,0123,7
CSV;
        $sizeLimitInBytes = 128;

        $csvFileMock = $this->createMock(AbstractCsv::class);
        $csvFileMock->method('toString')->willReturn($csvContent);
        $this->fileGenerator->method('generateFile')->willReturn($csvFileMock);

        Storage::fake(disk: 's3');

        $result = $this->csvExportService->exportToS3(
            data: [
                ['token' => '4444XXXXYYYY0001', 'expiration_date' => '0123', 'sequence' => 1],
                ['token' => '4444XXXXYYYY0002', 'expiration_date' => '0123', 'sequence' => 2],
                ['token' => '4444XXXXYYYY0003', 'expiration_date' => '0123', 'sequence' => 3],
                ['token' => '4444XXXXYYYY0004', 'expiration_date' => '0123', 'sequence' => 4],
                ['token' => '4444XXXXYYYY0005', 'expiration_date' => '0123', 'sequence' => 5],
                ['token' => '4444XXXXYYYY0006', 'expiration_date' => '0123', 'sequence' => 6],
                ['token' => '4444XXXXYYYY0007', 'expiration_date' => '0123', 'sequence' => 7],
            ],
            fileName: 'test.csv',
            sizeLimit: $sizeLimitInBytes
        );

        $this->assertFileExistsHasCorrectSizeAndContainsCorrectContent(
            sizeLimitInBytes: $sizeLimitInBytes,
            expectedContent: <<<CSV
4444XXXXYYYY0001,0123,1
4444XXXXYYYY0002,0123,2
4444XXXXYYYY0003,0123,3
4444XXXXYYYY0004,0123,4
4444XXXXYYYY0005,0123,5
CSV,
            fileName: 'test_1.csv'
        );

        $this->assertFileExistsHasCorrectSizeAndContainsCorrectContent(
            sizeLimitInBytes: $sizeLimitInBytes,
            expectedContent: <<<CSV
4444XXXXYYYY0006,0123,6
4444XXXXYYYY0007,0123,7
CSV,
            fileName: 'test_2.csv'
        );

        $this->assertTrue(condition: $result);
    }

    #[Test]
    public function it_exports_given_data_to_s3_as_expected_and_split_file_into_chunks_when_remainder_cannot_be_merged_to_last_chunk(): void
    {
        $csvContent = <<<CSV
4444XXXXYYYY0001,0123,1
4444XXXXYYYY0002,0123,2
4444XXXXYYYY0003,0123,100
4444XXXXYYYY0004,0123,111
CSV;
        $sizeLimitInBytes = 48;

        $csvFileMock = $this->createMock(AbstractCsv::class);
        $csvFileMock->method('toString')->willReturn($csvContent);
        $this->fileGenerator->method('generateFile')->willReturn($csvFileMock);

        Storage::fake(disk: 's3');

        $result = $this->csvExportService->exportToS3(
            data: [
                ['token' => '4444XXXXYYYY0001', 'expiration_date' => '0123', 'sequence' => 8],
                ['token' => '4444XXXXYYYY0002', 'expiration_date' => '0123', 'sequence' => 9],
                ['token' => '4444XXXXYYYY0003', 'expiration_date' => '0123', 'sequence' => 10],
                ['token' => '4444XXXXYYYY0004', 'expiration_date' => '0123', 'sequence' => 11],
            ],
            fileName: 'test.csv',
            sizeLimit: $sizeLimitInBytes
        );

        $this->assertFileExistsHasCorrectSizeAndContainsCorrectContent(
            sizeLimitInBytes: $sizeLimitInBytes,
            expectedContent: <<<CSV
4444XXXXYYYY0001,0123,1
4444XXXXYYYY0002,0123,2
CSV,
            fileName: 'test_1.csv'
        );

        $this->assertFileExistsHasCorrectSizeAndContainsCorrectContent(
            sizeLimitInBytes: $sizeLimitInBytes,
            expectedContent: <<<CSV
4444XXXXYYYY0003,0123,100
CSV,
            fileName: 'test_2.csv'
        );
        $this->assertFileExistsHasCorrectSizeAndContainsCorrectContent(
            sizeLimitInBytes: $sizeLimitInBytes,
            expectedContent: <<<CSV
4444XXXXYYYY0004,0123,111
CSV,
            fileName: 'test_3.csv'
        );

        $this->assertTrue(condition: $result);
    }

    public function assertFileExistsHasCorrectSizeAndContainsCorrectContent(
        int $sizeLimitInBytes,
        string $expectedContent,
        string $fileName
    ): void {
        Storage::disk(name: 's3')->assertExists(path: $fileName); // file name should have suffix with number of chunk
        $this->assertLessThanOrEqual(expected: $sizeLimitInBytes, actual: Storage::disk(name: 's3')->size(path: $fileName));
        $this->assertSame(expected: $expectedContent, actual: Storage::disk(name: 's3')->get(path: $fileName));
        $this->assertInfoLogAdded(fileName: $fileName, content: $expectedContent);
    }

    private function assertInfoLogAdded(string $fileName, string $content): void
    {
        Log::shouldReceive('info')->with(
            __('messages.export.file_uploaded_to_s3'),
            ['file_name' => $fileName, 'file_size' => strlen($content)]
        );
    }
}
