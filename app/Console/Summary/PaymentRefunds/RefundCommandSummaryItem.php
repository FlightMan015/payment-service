<?php

declare(strict_types=1);

namespace App\Console\Summary\PaymentRefunds;

use App\Helpers\MoneyHelper;

class RefundCommandSummaryItem
{
    private int $successful = 0;
    private int $successfulAmount = 0;
    private int $failed = 0;
    private int $failedAmount = 0;

    /**
     * @return void
     */
    public function incrementSuccessful(): void
    {
        $this->successful++;
    }

    /**
     * @return void
     */
    public function incrementFailed(): void
    {
        $this->failed++;
    }

    /**
     * @param int $amount
     *
     * @return void
     */
    public function increaseSuccessfulAmount(int $amount): void
    {
        $this->successfulAmount += $amount;
    }

    /**
     * @param int $amount
     *
     * @return void
     */
    public function increaseFailedAmount(int $amount): void
    {
        $this->failedAmount += $amount;
    }

    /**
     * @return int
     */
    public function getSuccessful(): int
    {
        return $this->successful;
    }

    /**
     * @return int
     */
    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * @return int
     */
    public function getSuccessfulAmount(): int
    {
        return $this->successfulAmount;
    }

    /**
     * @return int
     */
    public function getFailedAmount(): int
    {
        return $this->failedAmount;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'successful_count' => $this->getSuccessful(),
            'successful_amount' => MoneyHelper::convertToDecimal($this->getSuccessfulAmount()),
            'failed_count' => $this->getFailed(),
            'failed_amount' => MoneyHelper::convertToDecimal($this->getFailedAmount()),
        ];
    }
}
