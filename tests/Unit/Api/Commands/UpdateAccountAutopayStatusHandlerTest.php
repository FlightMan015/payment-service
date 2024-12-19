<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\UpdateAccountAutopayStatusCommand;
use App\Api\Commands\UpdateAccountAutopayStatusHandler;
use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class UpdateAccountAutopayStatusHandlerTest extends UnitTestCase
{
    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&AccountRepository $accountRepository */
    private AccountRepository $accountRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->accountRepository = $this->createMock(originalClassName: AccountRepository::class);
    }

    #[Test]
    public function it_updates_autopay_status_when_passing_autopay_payment_method_id(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);
        $this->accountRepository->method('find')->willReturn($account);

        $this->accountRepository->expects($this->once())->method('setAutoPayPaymentMethod')->with($account, $paymentMethod);

        $command = new UpdateAccountAutopayStatusCommand(
            accountId: $account->id,
            autopayPaymentMethodId: $paymentMethod->id
        );

        $this->handler()->handle(command: $command);
    }

    #[Test]
    public function it_updates_autopay_status_when_do_not_pass_autopay_payment_method_id(): void
    {
        $account = Account::factory()->withoutRelationships()->make();

        $this->accountRepository->method('find')->willReturn($account);
        $this->accountRepository->expects($this->once())->method('setAutoPayPaymentMethod')->with($account, null);

        $command = new UpdateAccountAutopayStatusCommand(
            accountId: $account->id,
            autopayPaymentMethodId: null
        );

        $this->handler()->handle(command: $command);
    }

    #[Test]
    public function it_throws_exception_when_account_cannot_be_found_in_database(): void
    {
        $this->accountRepository->method('find')
            ->willThrowException(exception: new ResourceNotFoundException(message: 'Account was not found'));

        $command = new UpdateAccountAutopayStatusCommand(accountId: Str::uuid()->toString(), autopayPaymentMethodId: null);

        $this->expectException(exception: ResourceNotFoundException::class);
        $this->expectExceptionMessage(message: 'Account was not found');

        $this->handler()->handle(command: $command);
    }

    #[Test]
    public function it_throws_exception_when_payment_method_does_not_belong_to_account(): void
    {
        $account = Account::factory()->withoutRelationships()->make();
        $anotherAccount = Account::factory()->withoutRelationships()->make();
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $anotherAccount]);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);
        $this->accountRepository->method('find')->willReturn($account);

        $this->accountRepository->expects($this->once())
            ->method('setAutoPayPaymentMethod')
            ->with($account, $paymentMethod)
            ->willThrowException(new PaymentMethodDoesNotBelongToAccountException(
                paymentMethodId: $paymentMethod->id,
                accountId: $account->id
            ));

        $command = new UpdateAccountAutopayStatusCommand(
            accountId: $account->id,
            autopayPaymentMethodId: $paymentMethod->id
        );

        $this->expectException(PaymentValidationException::class);

        $this->handler()->handle(command: $command);
    }

    private function handler(): UpdateAccountAutopayStatusHandler
    {
        return new UpdateAccountAutopayStatusHandler(
            paymentMethodRepository: $this->paymentMethodRepository,
            accountRepository: $this->accountRepository
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentMethodRepository, $this->accountRepository);
    }
}
