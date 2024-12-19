<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Rules;

use App\Models\Gateway;
use App\Rules\GatewayExistsAndActive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GatewayExistsAndActiveTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    #[DataProvider('dataForGateway')]
    public function gateway_rule_works_as_expected(bool $isEnabled, bool $isHidden, bool $shouldPass): void
    {
        $gateway = Gateway::factory()->create(attributes: [
            'id' => random_int(1000, 2000),
            'is_enabled' => $isEnabled,
            'is_hidden' => $isHidden,
        ]);

        $this->assertSame(
            expected: $shouldPass,
            actual: (new GatewayExistsAndActive())->passes(attribute: 'gateway_id', value: $gateway->id)
        );
    }

    public static function dataForGateway(): iterable
    {
        yield 'gateway is enabled and is not hidden' => [
            'isEnabled' => true,
            'isHidden' => false,
            'shouldPass' => true,
        ];

        yield 'gateway is enabled and but hidden' => [
            'isEnabled' => true,
            'isHidden' => true,
            'shouldPass' => false,
        ];

        yield 'gateway is disabled and is not hidden' => [
            'isEnabled' => false,
            'isHidden' => false,
            'shouldPass' => false,
        ];

        yield 'gateway is disabled and not hidden' => [
            'isEnabled' => false,
            'isHidden' => true,
            'shouldPass' => false,
        ];
    }

    #[Test]
    public function it_does_not_pass_when_the_gateway_does_not_exist(): void
    {
        $rule = new GatewayExistsAndActive();

        $this->assertFalse(condition: $rule->passes(attribute: 'gateway_id', value: Gateway::max(column: 'id') + 1));
    }
}
