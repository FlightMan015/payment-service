<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\FileGenerator\FileGenerator;

interface ExportService
{
    /**
     * @param FileGenerator $fileGenerator
     */
    public function __construct(FileGenerator $fileGenerator);

    /**
     * @param array $data
     * @param string $fileName
     * @param int|null $sizeLimit
     *
     * @return bool
     */
    public function exportToS3(array $data, string $fileName, int|null $sizeLimit = null): bool;
}
