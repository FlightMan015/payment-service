<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Helpers;

use App\Api\Requests\AbstractRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as IlluminateValidationValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

abstract class AbstractApiRequestTest extends UnitTestCase
{
    protected array $rules = [];

    /**
     * Instance of tested request
     *
     * @return AbstractRequest
     */
    abstract public function getTestedRequest(): AbstractRequest;

    /**
     * Data provider for negative cases
     *
     * @return array|\Iterator
     */
    abstract public static function getInvalidData(): array|\Iterator;

    /**
     * Data set which will pass the validation
     *
     * @return array
     */
    abstract public static function getValidDataSet(): array;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setRules();
    }

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        $this->assertFalse($validator->passes());
    }

    /**
     * Data provider for positive cases, it can be extended. By default, it returns valid data set
     *
     * @return array|\Iterator
     */
    public static function getValidData(): array|\Iterator
    {
        yield 'valid data' => [static::getValidDataSet()];
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function default_values_are_set_as_expected(): void
    {
        $defaultValues = $this->getDefaultsValues();

        if (empty($defaultValues)) {
            $this->expectNotToPerformAssertions();
            return;
        }

        $request = app()->make(abstract: $this->getTestedRequest()::class);
        $validatedData = $request->validated();

        foreach ($defaultValues as $key => $value) {
            $this->assertEquals(expected: $value, actual: $validatedData[$key]);
        }
    }

    protected function setRules(): void
    {
        $this->rules = $this->getTestedRequest()->rules();
    }

    protected static function getInvalidDataSet(string $field, mixed $value): array
    {
        $validDataSet = static::getValidDataSet();

        if (is_null($value)) {
            unset($validDataSet[$field]);
        } else {
            $validDataSet[$field] = $value;
        }

        return $validDataSet;
    }

    protected function getDefaultsValues(): array
    {
        return [];
    }

    protected function makeValidator(array $data, array $rules): IlluminateValidationValidator
    {
        return Validator::make($data, $rules);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->rules);
    }
}
