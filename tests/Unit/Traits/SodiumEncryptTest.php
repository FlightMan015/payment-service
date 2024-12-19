<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Helpers\SodiumEncryptHelper;
use App\Traits\SodiumEncrypt;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class SodiumEncryptTest extends UnitTestCase
{
    #[Test]
    public function it_encrypts_specified_model_attribute_when_trait_used(): void
    {
        $testKey = 'test_field';
        $testValue = 'test_field_value';

        $model = new class () extends Model {
            use SodiumEncrypt;

            protected array $encryptable = ['test_field'];
        };

        $model->setAttribute($testKey, $testValue);

        $encrypted = $model->getAttributes()[$testKey];

        $this->assertNotEquals($testValue, $encrypted);

        $decryptedValue = SodiumEncryptHelper::decrypt($encrypted);

        $this->assertEquals($testValue, $decryptedValue);
    }
}
