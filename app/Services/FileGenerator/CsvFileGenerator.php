<?php

declare(strict_types=1);

namespace App\Services\FileGenerator;

use League\Csv\AbstractCsv;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\Writer;

class CsvFileGenerator implements FileGenerator
{
    /**
     * @inheritDoc
     *
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function generateFile(array|string $data): AbstractCsv
    {
        return is_string(value: $data) ? $this->createCsvFromString(data: $data) : $this->createCsvFromArray(data: $data);
    }

    private function createCsvFromString(string $data): AbstractCsv
    {
        return Writer::createFromString($data);
    }

    /**
     * @param array $data
     *
     * @throws CannotInsertRecord
     * @throws Exception
     *
     * @return AbstractCsv
     */
    private function createCsvFromArray(array $data): AbstractCsv
    {
        $csv = Writer::createFromString();

        foreach ($data as $value) {
            $csv->insertOne($value);
        }

        return $csv;
    }

    /** @inheritdoc */
    public function contentType(): string
    {
        return 'text/csv';
    }
}
