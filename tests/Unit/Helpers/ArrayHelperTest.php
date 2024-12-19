<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ArrayHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\Unit\UnitTestCase;

class ArrayHelperTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('arraysProvider')]
    public function arrays_equals(array $array1, array $array2, bool $expectedResult): void
    {
        $this->assertEquals($expectedResult, ArrayHelper::arraysEquals($array1, $array2));
    }

    public static function arraysProvider(): iterable
    {
        yield 'Arrays are equal' => [
            [1, 2, 3],
            [3, 2, 1],
            true
        ];

        yield 'Arrays are not equal' => [
            [1, 2, 3],
            [4, 5, 6],
            false
        ];

        yield 'Arrays with duplicate values' => [
            [1, 2, 2, 3],
            [3, 2, 2, 1],
            true
        ];

        yield 'Arrays with different lengths' => [
            [1, 2, 3],
            [1, 2, 3, 4],
            false
        ];
    }

    #[Test]
    public function it_parse_worldpay_xml_response_to_array(): void
    {
        $rawWorldpayResponseLog = json_encode([0, WorldpayResponseStub::statusSuccess()]);

        $parsedResponse = ArrayHelper::parseGatewayResponseXmlToArray($rawWorldpayResponseLog);

        $this->assertNotEmpty($parsedResponse);
        $this->assertSame('Approved', data_get($parsedResponse, 'Response.ReportingData.Items.Item.ExpressResponseMessage'));
    }
}
