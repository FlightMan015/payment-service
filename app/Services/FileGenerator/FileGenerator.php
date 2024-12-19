<?php

declare(strict_types=1);

namespace App\Services\FileGenerator;

interface FileGenerator
{
    /**
     * @param array|string $data
     *
     * @return mixed
     */
    public function generateFile(array|string $data): mixed;

    /**
     * @return string
     */
    public function contentType(): string;
}
