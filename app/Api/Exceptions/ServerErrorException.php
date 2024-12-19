<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

class ServerErrorException extends AbstractAPIException
{
    public array $errors;

    /**
     * Override parent construct
     *
     * @param \Exception $exception
     *
     * @return void
     */
    public function __construct(\Exception $exception)
    {
        $this->errors = [
            ['detail' => $exception->getMessage()],
        ];

        if ($exception instanceof PaymentValidationException) {
            $this->errors = $exception->getErrors();
        }

        parent::__construct(message: $exception->getMessage() ?: __('messages.something_went_wrong'));
    }
}
