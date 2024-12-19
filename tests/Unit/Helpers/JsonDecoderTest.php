<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\JsonDecoder;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class JsonDecoderTest extends UnitTestCase
{
    #[Test]
    public function decode_with_valid_json_returns_array_as_expected(): void
    {
        $json = '{"name": "John", "age": 30, "city": "New York"}';

        $this->assertEquals(['name' => 'John', 'age' => 30, 'city' => 'New York'], JsonDecoder::decode(json: $json));
    }

    #[Test]
    public function decode_with_invalid_json_throws_exception(): void
    {
        $json = '{"name": "John", "age": 30, "city": "New York"';

        $this->expectException(\JsonException::class);
        JsonDecoder::decode(json: $json);
    }

    #[Test]
    public function decode_with_null_input_returns_null(): void
    {
        $this->assertNull(JsonDecoder::decode(json: null));
    }

    #[Test]
    public function encode_with_valid_data_returns_json_string(): void
    {
        $data = ['name' => 'John', 'age' => 30, 'city' => 'New York'];

        $this->assertEquals('{"name":"John","age":30,"city":"New York"}', JsonDecoder::encode(value: $data));
    }

    #[Test]
    public function encode_with_invalid_data_throws_exception(): void
    {
        $invalidData = fopen(filename: 'php://stdin', mode: 'rb');

        $this->expectException(\JsonException::class);
        JsonDecoder::encode(value: $invalidData);
    }

    #[Test]
    public function is_valid_json_string_with_valid_json_returns_true(): void
    {
        $validJson = '{"name": "John", "age": 30, "city": "New York"}';

        $this->assertTrue(JsonDecoder::isValidJsonString(value: $validJson));
    }

    #[Test]
    public function is_valid_json_string_with_invalid_json(): void
    {
        $json = '{"name": "John", "age": 30, "city": "New York"';
        $result = JsonDecoder::isValidJsonString(value: $json);

        $this->assertFalse($result);
    }

    #[Test]
    public function is_valid_json_string_with_non_string_input_returns_false(): void
    {
        $this->assertFalse(JsonDecoder::isValidJsonString(value: 123));
    }
}
