<?php

declare(strict_types=1);

namespace App\Exceptions;

abstract class AbstractPaymentProcessingException extends \RuntimeException
{
    /**
     * @param string $message
     * @param array $context
     */
    public function __construct(string $message, public array $context = [])
    {
        parent::__construct(message: $message);
    }
}
