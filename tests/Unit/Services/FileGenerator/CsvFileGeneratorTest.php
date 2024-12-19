<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Services\FileGenerator;

use App\Services\FileGenerator\CsvFileGenerator;
use League\Csv\AbstractCsv;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CsvFileGeneratorTest extends TestCase
{
    private CsvFileGenerator $csvFileGenerator;

    protected function setUp(): void
    {
        $this->csvFileGenerator = new CsvFileGenerator();
    }

    #[Test]
    public function it_generates_file_from_string(): void
    {
        $data = "1,John,Doe\n2,Jane,Smith";
        $result = $this->csvFileGenerator->generateFile($data);

        $this->assertInstanceOf(AbstractCsv::class, $result);
        $this->assertEquals($data, $result->toString());
    }

    #[Test]
    public function it_generates_file_from_array(): void
    {
        $data = [
            ['1', 'John', 'Doe'],
            ['2', 'Jane', 'Smith'],
        ];
        $expectedCsvString = "1,John,Doe\n2,Jane,Smith\n";

        $result = $this->csvFileGenerator->generateFile($data);

        $this->assertInstanceOf(AbstractCsv::class, $result);
        $this->assertEquals($expectedCsvString, $result->toString());
    }

    #[Test]
    public function it_generates_file_from_array_with_empty_data(): void
    {
        $data = [];
        $expectedCsvString = '';

        $result = $this->csvFileGenerator->generateFile($data);

        $this->assertInstanceOf(AbstractCsv::class, $result);
        $this->assertEquals($expectedCsvString, $result->toString());
    }

    #[Test]
    public function it_returns_correct_content_type(): void
    {
        $this->assertEquals('text/csv', $this->csvFileGenerator->contentType());
    }
}
