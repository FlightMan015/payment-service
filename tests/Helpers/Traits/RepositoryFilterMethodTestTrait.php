<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

trait RepositoryFilterMethodTestTrait
{
    #[Test]
    public function filter_method_returns_expected_pagination_result(): void
    {
        self::getEntity()->query()->delete(); // delete all previously created records from DB

        $totalRecords = random_int(min: 10, max: 100);
        $perPage = random_int(min: 5, max: min($totalRecords, 20));
        $pagesExpected = (int)ceil($totalRecords / $perPage);

        if (!method_exists(object_or_class: self::getEntity(), method: 'factory')) {
            $this->markTestSkipped(message: 'Entity does not have a factory method');
        }

        /** @var Collection<int, Model> $entities */
        $entities = self::getEntity()->factory()->count(count: $totalRecords)->create($this->modelAttributes());

        $results = $this->repository->filter(filter: [
            'id' => $entities->pluck('id')->toArray(),
            'per_page' => $perPage,
            'page' => 1,
        ]);

        $this->assertCount(expectedCount: $perPage, haystack: $results);
        $this->assertSame(expected: $pagesExpected, actual: $results->lastPage());
        $this->assertSame(expected: $totalRecords, actual: $results->total());
    }

    abstract protected function getEntity(): Model;
    abstract protected function getRepository(): mixed;
    protected function modelAttributes(): array
    {
        return [];
    }
}
