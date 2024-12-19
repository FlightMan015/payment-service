<?php

declare(strict_types=1);

namespace App\Services\FileReader;

interface FileReader
{
    /**
     * @param string $content
     *
     * @return mixed
     */
    public function fromString(string $content): mixed;
}
