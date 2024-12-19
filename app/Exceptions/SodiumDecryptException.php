<?php

declare(strict_types=1);

namespace App\Exceptions;

class SodiumDecryptException extends \SodiumException
{
    public function __construct()
    {
        parent::__construct(message: __('messages.sodium.decryption_error'));
    }
}
