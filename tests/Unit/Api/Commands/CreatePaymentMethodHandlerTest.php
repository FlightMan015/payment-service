<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CreatePaymentMethodCommand;
use App\Api\Commands\CreatePaymentMethodHandler;
use App\Api\Exceptions\InvalidPaymentMethodException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentAttemptedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\CreditCardTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\LostConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\Unit\UnitTestCase;

class CreatePaymentMethodHandlerTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    /** @var MockObject&AccountRepository $accountRepository */
    private AccountRepository $accountRepository;
    /** @var MockObject&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;
    private CreatePaymentMethodCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCommand();

        $this->createAccountRepositoryMock();
        $this->createPaymentProcessorMock();
        $this->createPaymentMethodRepositoryMock();
        $this->mockWorldPayGuzzleCall();

        $this->mockWorldPayCredentialsRepository();

        Event::fake();
    }

    #[Test]
    public function handle_method_throws_exception_if_account_is_not_found(): void
    {
        $this->expectException(exception: UnprocessableContentException::class);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);

        $this->handler()->handle(command: $this->command);
    }

    #[Test]
    public function handle_method_logs_and_throws_exception_if_db_throws_exception_when_creating_payment_method(): void
    {
        $this->createAccountRepositoryMock(account: Account::factory()->withoutRelationships()->make());
        $exception = new LostConnectionException(message: 'Connection issues');
        $this->paymentMethodRepository->method('create')->willThrowException($exception);

        $this->expectException(LostConnectionException::class);
        Log::shouldReceive('shareContext')->once()->with([
            'account_id' => $this->command->accountId,
            'request' => $this->command->toArray(),
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler()->handle(command: $this->command);
    }

    #[Test]
    public function handle_method_throws_and_logs_exception_if_db_throws_exception_when_creating_payment(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->createAccountRepositoryMock(account: $account);

        $exception = new LostConnectionException(message: 'Connection issues');

        $expectedId = Str::uuid()->toString();
        $this->mockPaymentRepositoryExpectedResult(
            expectedId: $expectedId,
            withPaymentCreating: false,
            account: $account
        );
        $this->paymentRepository->method('create')->willThrowException($exception);

        $this->expectException(LostConnectionException::class);
        Log::shouldReceive('shareContext')->once()->with([
            'account_id' => $this->command->accountId,
            'request' => $this->command->toArray()
        ]);
        Log::shouldReceive('shareContext')->once()->with(['payment_method_id' => $expectedId]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler()->handle(command: $this->command);
    }

    #[Test]
    public function handle_method_throws_and_logs_exception_if_payment_processor_returns_false_on_authorize(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->createAccountRepositoryMock(account: $account);

        /** @var MockObject|PaymentProcessor $paymentProcessor */
        $paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $paymentProcessor->method('authorize')->willReturn(false);

        $expectedId = Str::uuid()->toString();
        $this->mockPaymentRepositoryExpectedResult(expectedId: $expectedId, account: $account);

        $this->expectException(InvalidPaymentMethodException::class);
        Log::shouldReceive('debug'); // worldpay debug logs
        Log::shouldReceive('shareContext')->once()->with([
            'account_id' => $this->command->accountId,
            'request' => $this->command->toArray()
        ]);
        Log::shouldReceive('shareContext')->once()->with(['payment_method_id' => $expectedId]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler(paymentProcessor: $paymentProcessor)->handle(command: $this->command);
    }

    #[Test]
    public function it_returns_dto_result_object_with_correct_id_in_the_end_of_the_process(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->createAccountRepositoryMock(account: $account);

        $expectedId = Str::uuid()->toString();
        $this->mockPaymentRepositoryExpectedResult(expectedId: $expectedId, account: $account);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $dto = $this->handler()->handle(command: $this->command);

        Event::assertDispatched(event: PaymentAttemptedEvent::class);

        $this->assertSame($expectedId, $dto->paymentMethodId);
    }

    #[Test]
    public function it_returns_dto_result_object_with_correct_id_and_not_dispatch_payment_event_when_should_skip_gateway_validation(): void
    {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);
        $this->createAccountRepositoryMock(account: $account);

        $expectedId = Str::uuid()->toString();
        $this->mockPaymentRepositoryExpectedResult(expectedId: $expectedId, withPaymentCreating: false, account: $account);

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $dto = $this->handler()->handle(command: new CreatePaymentMethodCommand(
            accountId: $account->id,
            type: PaymentTypeEnum::CC,
            gateway: PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT,
            firstName: 'Dang',
            lastName: 'Ng',
            achAccountNumber: null,
            achRoutingNumber: null,
            achAccountLastFour: null,
            achAccountType: null,
            achBankName: null,
            creditCardToken: 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
            creditCardType: CreditCardTypeEnum::VISA,
            creditCardExpirationMonth: 1,
            creditCardExpirationYear: (int)date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
            creditCardLastFour: '6541',
            addressLine1: 'Address line 1',
            addressLine2: null,
            email: 'dang.nguyen@goaptive.com',
            city: 'Utah',
            province: 'UT',
            postalCode: '01103',
            countryCode: 'US',
            isPrimary: true,
            shouldSkipGatewayValidation: true
        ));

        Event::assertNotDispatched(event: PaymentAttemptedEvent::class);
        $this->assertSame($expectedId, $dto->paymentMethodId);
    }

    private function createCommand(
        PaymentTypeEnum $paymentType = PaymentTypeEnum::CC,
        PaymentGatewayEnum|null $gateway = null,
        bool $isPrimary = false
    ): void {
        $paymentGateway = PaymentGatewayEnum::cases();
        shuffle($paymentGateway);

        $this->command = new CreatePaymentMethodCommand(
            accountId: Str::uuid()->toString(),
            type: $paymentType,
            gateway: $gateway ?? $paymentGateway[0],
            firstName: 'Ivan',
            lastName: 'Vasechko',
            achAccountNumber: null,
            achRoutingNumber: null,
            achAccountLastFour: null,
            achAccountType: null,
            achBankName: null,
            creditCardToken: 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
            creditCardType: CreditCardTypeEnum::MASTERCARD,
            creditCardExpirationMonth: 1,
            creditCardExpirationYear: (int)date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
            creditCardLastFour: '6548',
            addressLine1: 'Address line 1',
            addressLine2: null,
            email: 'ivan.vasechko@goaptive.com',
            city: 'Utah',
            province: 'UT',
            postalCode: '01103',
            countryCode: 'US',
            isPrimary: $isPrimary,
            shouldSkipGatewayValidation: false
        );
    }

    private function createAccountRepositoryMock(Account|null $account = null): void
    {
        $this->accountRepository = $this->createMock(originalClassName: AccountRepository::class);
        $this->accountRepository->method('find')->willReturn(value: $account);
    }

    private function createPaymentProcessorMock(): void
    {
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $this->paymentProcessor->method('authorize')->willReturn(value: true);
    }

    private function createPaymentMethodRepositoryMock(): void
    {
        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);
    }

    private function mockWorldPayGuzzleCall(): void
    {
        $guzzle = $this->createMock(GuzzleClient::class);
        $guzzle->method('post')->willReturn(new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            body: WorldpayResponseStub::paymentAccountQuerySuccess(),
        ));

        $this->app->instance(abstract: GuzzleClient::class, instance: $guzzle);
    }

    private function handler(
        AccountRepository|null $accountRepository = null,
        PaymentProcessor|null $paymentProcessor = null,
        PaymentMethodRepository|null $paymentMethodRepository = null,
        PaymentRepository|null $paymentRepository = null,
    ): CreatePaymentMethodHandler {
        return new CreatePaymentMethodHandler(
            accountRepository: $accountRepository ?? $this->accountRepository,
            paymentProcessor: $paymentProcessor ?? $this->paymentProcessor,
            paymentMethodRepository: $paymentMethodRepository ?? $this->paymentMethodRepository,
            paymentRepository: $paymentRepository ?? $this->paymentRepository,
        );
    }

    private function mockPaymentRepositoryExpectedResult(
        string $expectedId,
        bool $withPaymentCreating = true,
        Account|null $account = null
    ): void {
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(attributes: [
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'id' => $expectedId,
            'payment_type_id' => PaymentTypeEnum::CC,
            'external_ref_id' => null,
        ], relationships: ['account' => $account]);

        $this->paymentMethodRepository->method('create')->willReturn($paymentMethod);

        if ($withPaymentCreating) {
            $payment =  Payment::factory()->makeWithRelationships(attributes: [
                'payment_status_id' => PaymentStatusEnum::AUTHORIZING,
                'id' => 123123,
            ], relationships: ['account' => $account, 'paymentMethod' => $paymentMethod]);
            $this->paymentRepository->method('create')->willReturn(value: $payment);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->accountRepository,
            $this->paymentProcessor,
            $this->command,
            $this->paymentMethodRepository,
            $this->paymentRepository
        );
    }
}
