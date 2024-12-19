<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Gateway;
use App\Traits\PartiallyReplicableModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PartiallyReplicableModelTest extends UnitTestCase
{
    #[Test]
    public function it_ignores_given_fields_when_replicating_model(): void
    {
        $modelObject1 = new class () extends Gateway {
            use PartiallyReplicableModel;

            protected array $ignoreWhenReplicating = ['description'];
        };

        $modelObject1->name = 'Test';
        $modelObject1->description = 'Description';

        $modelObject2 = $modelObject1->replicate();

        $this->assertSame($modelObject1->name, $modelObject2->name);
        $this->assertNotEquals($modelObject1->description, $modelObject2->description);
        $this->assertNull($modelObject2->description);
    }
}
