<?php

declare(strict_types=1);

namespace Tests;

trait ProcessesDataProvidersClosures
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->usesDataProvider()) {
            $this->setData(dataName: $this->dataName(), data: $this->processData());
        }
    }

    private function processData(): array
    {
        $data = $this->providedData();

        // Iterate through provided data
        foreach ($data as $parameterKey => $parameters) {
            // If parameters is an array, check and process each value
            if (is_array($parameters)) {
                foreach ($parameters as $key => $value) {
                    // If the value is callable, replace it with the result of the function
                    if (is_callable($value)) {
                        $data[$parameterKey][$key] = $value();
                    }
                }
            }

            // If the parameters itself is callable, replace it with the result of the function
            if (is_callable($parameters)) {
                $data[$parameterKey] = $parameters();
            }
        }

        return $data;
    }
}
