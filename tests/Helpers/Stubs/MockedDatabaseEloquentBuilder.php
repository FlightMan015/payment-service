<?php

declare(strict_types=1);

namespace Tests\Helpers\Stubs;

class MockedDatabaseEloquentBuilder
{
    protected array $mocks = [];

    /**
     * @param int|string $name
     * @param array $arguments
     *
     * @return static
     */
    public function __call(int|string $name, array $arguments): static
    {
        if (array_key_exists($name, $this->mocks)) {
            return $this->mocks[$name];
        }

        return $this;
    }

    /**
     * @param string $method
     * @param mixed $value
     *
     * @return void
     */
    public function mockResult(string $method, mixed $value): void
    {
        $this->mocks = [
            $method => $value,
        ];
    }
}
