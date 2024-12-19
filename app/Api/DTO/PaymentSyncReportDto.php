<?php

declare(strict_types=1);

namespace App\Api\DTO;

readonly class PaymentSyncReportDto
{
    /**
     * @param int $numberUnprocessed
     * @param string $message
     */
    public function __construct(
        public int $numberUnprocessed,
        public string $message,
    ) {
    }
}
