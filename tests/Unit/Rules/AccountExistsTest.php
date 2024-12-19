<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Api\Repositories\CRM\AccountRepository;
use App\Rules\AccountExists;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class AccountExistsTest extends UnitTestCase
{
    #[Test]
    public function valid_account_exists_and_rule_passes(): void
    {
        $rule = new AccountExists(
            $this->mockAccountRepositoryWithResult(method: 'exists', expectedResult: true)
        );

        $this->assertTrue(condition: $rule->passes(attribute: 'account_id', value: Str::uuid()->toString()));
    }

    #[Test]
    public function invalid_account_does_not_exist_and_rule_does_not_pass(): void
    {
        $rule = new AccountExists(
            $this->mockAccountRepositoryWithResult(method: 'exists', expectedResult: false)
        );
        $this->assertFalse(condition: $rule->passes(attribute: 'account_id', value: Str::uuid()->toString()));
    }

    #[Test]
    public function validation_error_message_returns_as_expected(): void
    {
        $rule = new AccountExists(
            $this->mockAccountRepositoryWithResult(method: 'exists', expectedResult: false)
        );

        $this->assertEquals(expected: __('messages.account.not_found_in_db'), actual: $rule->message());
    }

    private function mockAccountRepositoryWithResult(string $method, mixed $expectedResult): AccountRepository
    {
        /**
         * @var AccountRepository|MockObject $accountRepository
         */
        $accountRepository = $this->createMock(originalClassName: AccountRepository::class);
        $accountRepository->method($method)->willReturn($expectedResult);

        return $accountRepository;
    }
}
