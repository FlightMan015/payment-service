<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\SodiumDecryptException;

class SodiumEncryptHelper
{
    /**
     * @param string $value
     *
     * @throws \SodiumException
     * @throws \Exception
     *
     * @return string
     */
    public static function encrypt(string $value): string
    {
        $secretKey = sodium_hex2bin(config('sodium.secret_key'));
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($value, $nonce, $secretKey);
        $encryptedValue = sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_ORIGINAL);

        self::flushMemory($secretKey, $nonce, $value, $ciphertext);

        return $encryptedValue;
    }

    /**
     * @param string $encryptedValue
     *
     * @throws \SodiumException
     *
     * @return string
     */
    public static function decrypt(string $encryptedValue): string
    {
        $secretKey = sodium_hex2bin(config('sodium.secret_key'));
        $ciphertext = sodium_base642bin($encryptedValue, SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = mb_substr($ciphertext, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($ciphertext, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $decryptedValue = sodium_crypto_secretbox_open($ciphertext, $nonce, $secretKey);

        if ($decryptedValue === false) {
            throw new SodiumDecryptException();
        }

        self::flushMemory($secretKey, $nonce, $encryptedValue, $ciphertext);

        return $decryptedValue;
    }

    /**
     * @param mixed ...$vars
     *
     * @throws \SodiumException
     *
     * @return void
     */
    private static function flushMemory(&...$vars): void
    {
        foreach ($vars as &$var) {
            sodium_memzero($var);
        }
    }
}
