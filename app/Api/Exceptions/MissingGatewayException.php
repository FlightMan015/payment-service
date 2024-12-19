<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

class MissingGatewayException extends AbstractAPIException
{
    public function __construct()
    {
        parent::__construct(message: __('messages.gateway.missing'));
    }
}
