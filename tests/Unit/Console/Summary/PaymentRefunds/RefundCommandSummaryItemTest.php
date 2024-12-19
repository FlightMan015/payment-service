<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Summary\PaymentRefunds;

use App\Console\Summary\PaymentRefunds\RefundCommandSummaryItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class RefundCommandSummaryItemTest extends UnitTestCase
{
    #[Test]
    public function it_increments_successful_as_expected(): void
    {
        $summaryItem = new RefundCommandSummaryItem();
        $summaryItem->incrementSuccessful();
        $this->assertEquals(1, $summaryItem->getSuccessful());
        $summaryItem->incrementSuccessful();
        $this->assertEquals(2, $summaryItem->getSuccessful());
    }

    #[Test]
    public function it_increments_failed_as_expected(): void
    {
        $summaryItem = new RefundCommandSummaryItem();
        $summaryItem->incrementFailed();
        $this->assertEquals(1, $summaryItem->getFailed());
        $summaryItem->incrementFailed();
        $this->assertEquals(2, $summaryItem->getFailed());
    }

    #[Test]
    public function it_increases_successful_amount_as_expected(): void
    {
        $summaryItem = new RefundCommandSummaryItem();
        $summaryItem->increaseSuccessfulAmount(100);
        $this->assertEquals(100, $summaryItem->getSuccessfulAmount());
        $summaryItem->increaseSuccessfulAmount(50);
        $this->assertEquals(150, $summaryItem->getSuccessfulAmount());
    }

    #[Test]
    public function it_increases_failed_amount_as_expected(): void
    {
        $summaryItem = new RefundCommandSummaryItem();
        $summaryItem->increaseFailedAmount(50);
        $this->assertEquals(50, $summaryItem->getFailedAmount());
        $summaryItem->increaseFailedAmount(100);
        $this->assertEquals(150, $summaryItem->getFailedAmount());
    }

    #[Test]
    public function it_returns_expected_array(): void
    {
        $summaryItem = new RefundCommandSummaryItem();
        $summaryItem->incrementSuccessful();
        $summaryItem->increaseSuccessfulAmount(100);
        $summaryItem->incrementFailed();
        $summaryItem->increaseFailedAmount(50);

        $expectedArray = [
            'successful_count' => 1,
            'successful_amount' => 1.00,
            'failed_count' => 1,
            'failed_amount' => 0.50,
        ];

        $this->assertEquals($expectedArray, $summaryItem->toArray());
    }
}
