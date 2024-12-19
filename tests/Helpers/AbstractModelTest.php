<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

abstract class AbstractModelTest extends TestCase
{
    use DatabaseTransactions;
    use WithFaker;

    abstract protected function getTableName(): string;

    abstract protected function getColumnList(): array;

    #[Test]
    public function database_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns(table: $this->getTableName(), columns: $this->getColumnList()));
    }
}
