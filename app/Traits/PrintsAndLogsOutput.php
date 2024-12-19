<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait PrintsAndLogsOutput
{
    private function printAndLogInfo(string $message): void
    {
        $this->info($message);
        Log::info($message);
    }

    private function printAndLogError(string $message): void
    {
        $this->error($message);
        Log::error($message);
    }
}
