<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Foundation\Http\FormRequest;

trait RequestDefaultValuesTrait
{
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], mixed $content = null)
    {
        if (!($this instanceof FormRequest)) {
            throw new \RuntimeException(message: 'This trait can only be used in classes that extends FormRequest');
        }

        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    protected function prepareForValidation(): void
    {
        // add default values
        if (method_exists($this, 'defaults')) {
            foreach ($this->defaults() as $key => $defaultValue) {
                if (!$this->has($key)) {
                    $this->merge([$key => $defaultValue]);
                }
            }
        }
    }
}
