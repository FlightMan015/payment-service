<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

trait AllowTrailingSlashInHttpRequestsTrait
{
    use MakesHttpRequests;

    /**
     * Turn the given URI into a fully qualified URL without trimming slash.
     *
     * @param string $uri
     *
     * @return string
     */
    protected function prepareUrlForRequest($uri): string
    {
        return $uri;
    }
}
