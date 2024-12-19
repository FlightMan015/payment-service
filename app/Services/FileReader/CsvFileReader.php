<?php

declare(strict_types=1);

namespace App\Services\FileReader;

use League\Csv\Exception;
use League\Csv\Reader;

class CsvFileReader implements FileReader
{
    /**
     * @param string $content
     *
     * @throws Exception
     *
     * @return \Iterator
     */
    public function fromString(string $content): \Iterator
    {
        return Reader::createFromString(content: $content)->getRecords();
    }
}
