<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\InvalidCustomRequestClass;
use Tests\Unit\UnitTestCase;

class RequestDefaultValuesTraitTest extends UnitTestCase
{
    #[Test]
    public function trait_cannot_be_used_by_class_that_does_not_extent_form_request(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This trait can only be used in classes that extends FormRequest');

        new InvalidCustomRequestClass();
    }
}
