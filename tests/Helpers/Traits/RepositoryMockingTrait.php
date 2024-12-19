<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use PHPUnit\Framework\MockObject\Exception;

trait RepositoryMockingTrait
{
    /**
     * @param class-string $repositoryClass
     * @param string $method
     * @param \Exception $exception
     *
     * @throws Exception
     *
     * @return void
     */
    public function repositoryWillThrowException(string $repositoryClass, string $method, \Exception $exception): void
    {
        $repository = $this->createMock($repositoryClass);
        $repository->method($method)->willThrowException($exception);
        $this->app->instance(abstract: $repositoryClass, instance: $repository);
    }

    /**
     * @param class-string $repositoryClass
     * @param string $method
     * @param mixed $value
     *
     * @throws Exception
     *
     * @return void
     */
    public function repositoryWillReturn(string $repositoryClass, string $method, mixed $value): void
    {
        $repository = $this->createMock($repositoryClass);
        $repository->method($method)->willReturn($value);
        $this->app->instance(abstract: $repositoryClass, instance: $repository);
    }

    /**
     * @param class-string $repositoryClass
     * @param array $expectations
     *
     * @return void
     */
    public function repositoryWillReturnForConsecutiveCalls(string $repositoryClass, array $expectations): void
    {
        $repositoryMock = \Mockery::mock($repositoryClass);
        foreach ($expectations as $method => $value) {
            if (is_array($value)) {
                $repositoryMock->expects($value['method'])->andReturn($value['value']);
            } else {
                $repositoryMock->expects($method)->andReturn($value);
            }
        }
        $this->app->instance($repositoryClass, $repositoryMock);
    }
}
