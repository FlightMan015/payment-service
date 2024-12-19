<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Enums;

use App\PaymentProcessor\Enums\OperationFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class OperationFieldsTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('nameProvider')]
    public function it_validates_name_on_account_correctly_using_the_regex(
        string $name,
        bool $shouldMatch
    ): void {
        $regex = OperationFields::NAME_ON_ACCOUNT->regex(modifier: 'u');

        if ($shouldMatch) {
            $this->assertMatchesRegularExpression(pattern: $regex, string: $name);
        } else {
            $this->assertDoesNotMatchRegularExpression(pattern: $regex, string: $name);
        }
    }

    public static function nameProvider(): iterable
    {
        // valid names
        yield 'simple name' => ['name' => 'John Doe', 'shouldMatch' => true];
        yield "contains ' character" => ['name' => "Mary O'Brien", 'shouldMatch' => true];
        yield 'some Spanish name' => ['name' => 'José García', 'shouldMatch' => true];
        yield 'double-name, contains a hyphen, which is allowed' => ['name' => 'Anna-Maria Smith', 'shouldMatch' => true];
        yield 'japanese name (Shigeru Miyamoto)' => ['name' => '宮本 茂', 'shouldMatch' => true];
        yield 'cyrillic name (Vasechko Ivan)' => ['name' => 'Васечко Іван', 'shouldMatch' => true];
        yield 'name with the period (.) symbol' => ['name' => 'Mr. John Doe', 'shouldMatch' => true];
        yield 'contains an exclamation mark' => ['name' => "Mary O'Brien!", 'shouldMatch' => true];
        yield 'contains an emoji, which is a control character' => ['name' => 'José García🌟', 'shouldMatch' => true];
        yield 'contains a caret' => ['name' => 'Li ^ Wei', 'shouldMatch' => true];
        yield 'contains a newline character, a control character' => ['name' => 'John\nDoe', 'shouldMatch' => true];

        // invalid names
        yield 'contains backticks and is a classic example of SQL injection attempt' => ['name' => "`Robert'); DROP TABLE customers;--`", 'shouldMatch' => false];
    }

    #[Test]
    #[DataProvider('addressProvider')]
    public function it_validates_address_fields_correctly_using_the_regex(
        string $address,
        bool $shouldMatch
    ): void {
        $addressLine1Regex = OperationFields::NAME_ON_ACCOUNT->regex(modifier: 'u');

        if ($shouldMatch) {
            $this->assertMatchesRegularExpression(pattern: $addressLine1Regex, string: $address);
        } else {
            $this->assertDoesNotMatchRegularExpression(pattern: $addressLine1Regex, string: $address);
        }

        $addressLine2Regex = OperationFields::NAME_ON_ACCOUNT->regex(modifier: 'u');

        if ($shouldMatch) {
            $this->assertMatchesRegularExpression(pattern: $addressLine2Regex, string: $address);
        } else {
            $this->assertDoesNotMatchRegularExpression(pattern: $addressLine2Regex, string: $address);
        }
    }

    public static function addressProvider(): iterable
    {
        // valid addresses
        yield 'simple address' => ['address' => '123 Main St', 'shouldMatch' => true];
        yield 'contains a hyphen' => ['address' => '123-456 Main St', 'shouldMatch' => true];
        yield 'contains a period' => ['address' => '123.456 Main St', 'shouldMatch' => true];
        yield 'contains a comma' => ['address' => '123,456 Main St', 'shouldMatch' => true];
        yield 'contains a newline character, a control character' => ['address' => '123\nMain St', 'shouldMatch' => true];
        yield 'contains a backslash' => ['address' => '123\\456 Main St', 'shouldMatch' => true];
        yield 'contains a forward slash' => ['address' => '123/456 Main St', 'shouldMatch' => true];
        yield 'contains a colon' => ['address' => '123:456 Main St', 'shouldMatch' => true];
        yield 'contains a semicolon' => ['address' => '123;456 Main St', 'shouldMatch' => true];
        yield 'contains a question mark' => ['address' => '123?456 Main St', 'shouldMatch' => true];
        yield 'contains an exclamation mark' => ['address' => '123!456 Main St', 'shouldMatch' => true];
        yield 'contains an emoji, which is a control character' => ['address' => '123 Main St🌟', 'shouldMatch' => true];
        yield 'contains a caret' => ['address' => '123^456 Main St', 'shouldMatch' => true];
        yield 'contains a number sign' => ['address' => '123#456 Main St', 'shouldMatch' => true];

        // invalid addresses
        yield 'contains backticks and is a classic example of SQL injection attempt' => ['address' => "`Main St'); DROP TABLE addresses;--`", 'shouldMatch' => false];
    }

    #[Test]
    #[DataProvider('cityProvider')]
    public function it_validates_city_correctly_using_the_regex(
        string $city,
        bool $shouldMatch
    ): void {
        $cityRegex = OperationFields::CITY->regex(modifier: 'u');

        if ($shouldMatch) {
            $this->assertMatchesRegularExpression(pattern: $cityRegex, string: $city);
        } else {
            $this->assertDoesNotMatchRegularExpression(pattern: $cityRegex, string: $city);
        }
    }

    public static function cityProvider(): iterable
    {
        // valid cities
        yield 'simple city' => ['city' => 'New York', 'shouldMatch' => true];
        yield 'contains a hyphen' => ['city' => 'San-Francisco', 'shouldMatch' => true];
        yield 'contains a period' => ['city' => 'St. Louis', 'shouldMatch' => true];
        yield 'contains a newline character, a control character' => ['city' => "San\nFrancisco", 'shouldMatch' => true];
        yield 'contains a backslash' => ['city' => 'San\\Francisco', 'shouldMatch' => true];
        yield 'contains a forward slash' => ['city' => 'San/Francisco', 'shouldMatch' => true];
        yield 'contains a colon' => ['city' => 'San:Francisco', 'shouldMatch' => true];
        yield 'contains a semicolon' => ['city' => 'San;Francisco', 'shouldMatch' => true];
        yield 'contains a question mark' => ['city' => 'San?Francisco', 'shouldMatch' => true];
        yield 'contains an exclamation mark' => ['city' => 'San!Francisco', 'shouldMatch' => true];
        yield 'contains an emoji, which is a control character' => ['city' => 'San Francisco🌟', 'shouldMatch' => true];

        // invalid cities
        yield 'contains backticks and is a classic example of SQL injection attempt' => ['city' => "`Francisco'); DROP TABLE cities;--`", 'shouldMatch' => false];
    }
}
