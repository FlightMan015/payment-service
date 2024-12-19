<?php

declare(strict_types=1);

namespace App\Traits;

use App\Helpers\SodiumEncryptHelper;

trait SodiumEncrypt
{
    /**
     * @param string $key
     * @param mixed $value
     *
     * @throws \SodiumException
     *
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        if ($this->isEncryptable($key) && !empty($value)) {
            $value = $this->encrypt((string)$value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @param string $value
     *
     * @throws \SodiumException
     * @throws \Exception
     */
    private function encrypt(string $value): string
    {
        return SodiumEncryptHelper::encrypt($value);
    }

    private function isEncryptable(string $key): bool
    {
        return in_array($key, $this->encryptable);
    }
}
