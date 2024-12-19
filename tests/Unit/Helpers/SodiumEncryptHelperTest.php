<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Exceptions\SodiumDecryptException;
use App\Helpers\SodiumEncryptHelper;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class SodiumEncryptHelperTest extends UnitTestCase
{
    #[Test]
    public function it_encrypts_the_value(): void
    {
        $rawValue = '12345678';

        $encryptedValue = SodiumEncryptHelper::encrypt($rawValue);

        $this->assertNotEquals($rawValue, $encryptedValue);
    }

    #[Test]
    public function it_decrypts_the_value(): void
    {
        $rawValue = '12345678';

        $encryptedValue = SodiumEncryptHelper::encrypt($rawValue);
        $decryptedValue = SodiumEncryptHelper::decrypt($encryptedValue);

        $this->assertEquals($rawValue, $decryptedValue);
    }

    #[Test]
    public function it_throws_exception_when_decryption_returns_false(): void
    {
        $rawValue = '12345678';

        $encryptedValue = SodiumEncryptHelper::encrypt($rawValue);

        // change the sodium key after value was already encrypted
        Config::set('sodium.secret_key', sodium_bin2hex(sodium_crypto_secretbox_keygen()));

        $this->expectException(SodiumDecryptException::class);
        $this->expectExceptionMessage(__('messages.sodium.decryption_error'));

        SodiumEncryptHelper::decrypt($encryptedValue);
    }
}
