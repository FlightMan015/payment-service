<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\UpdatePaymentMethodCommand;
use App\Api\Commands\UpdatePaymentMethodHandler;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentAttemptedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\FakeEventDispatcherTrait;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\Unit\UnitTestCase;

class UpdatePaymentMethodHandlerTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;
    use FakeEventDispatcherTrait;

    /** @var MockObject&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    /** @var MockObject&UpdatePaymentMethodCommand $paymentProcessor */
    private UpdatePaymentMethodCommand $command;
    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new UpdatePaymentMethodCommand(
            firstName: 'First name',
            lastName: 'Last name',
            addressLine1: 'Address line 1',
            addressLine2: null,
            email: 'exxample@goaptive.com',
            city: 'Utah',
            province: 'UT',
            postalCode: '01103',
            countryCode: 'US',
            creditCardExpirationMonth: 12,
            creditCardExpirationYear: 2028,
            isPrimary: null
        );
        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);

        $this->mockWorldPayCredentialsRepository();

        PaymentMethod::creating(static fn () => false);
        PaymentMethod::saving(static fn () => false);
        Payment::creating(static fn () => false);

        $this->fakeEvents();
    }

    #[Test]
    public function it_returns_true_when_update_payment_method_as_primary(): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: [
            'external_ref_id' => random_int(1000, 10000),
            'is_primary' => false
        ]);

        $this->mockWorldPayGuzzleUpdatePayment();
        $this->createPaymentProcessorMock(authorizedResult: true);
        $command = new UpdatePaymentMethodCommand(
            firstName: 'First name',
            lastName: 'Last name',
            addressLine1: 'Address line 1',
            addressLine2: null,
            email: 'exxample@goaptive.com',
            city: 'Utah',
            province: 'UT',
            postalCode: '01103',
            countryCode: 'US',
            creditCardExpirationMonth: null,
            creditCardExpirationYear: null,
            isPrimary: true
        );

        $this->assertFalse($paymentMethod->is_primary);

        /** @var MockInterface&PaymentMethod $paymentMethod */
        $paymentMethod = Mockery::mock($paymentMethod)->makePartial();
        $paymentMethod->allows('makePrimary')->withNoArgs();

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->assertTrue(condition: $this->handler()->handle(paymentMethod: $paymentMethod, command: $command));
    }

    #[Test]
    public function it_unset_primary_and_returns_true_when_update_payment_method_as_non_primary(): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: [
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'external_ref_id' => random_int(1000, 10000),
            'is_primary' => true,
        ]);

        $this->mockWorldPayGuzzleUpdatePayment();
        $this->createPaymentProcessorMock(authorizedResult: true);
        $command = new UpdatePaymentMethodCommand(
            firstName: 'First name',
            lastName: 'Last name',
            addressLine1: 'Address line 1',
            addressLine2: null,
            email: 'exxample@goaptive.com',
            city: 'Utah',
            province: 'UT',
            postalCode: '01103',
            countryCode: 'US',
            creditCardExpirationMonth: null,
            creditCardExpirationYear: null,
            isPrimary: false
        );

        /** @var MockInterface&PaymentMethod $paymentMethod */
        $paymentMethod = Mockery::mock($paymentMethod)->makePartial();
        $paymentMethod->allows('getAttribute')->with('is_primary')->andReturnTrue();
        $paymentMethod->allows('unsetPrimary')->withNoArgs();

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->assertTrue($paymentMethod->is_primary);
        $this->assertFalse($command->isPrimary);

        $this->assertTrue($this->handler()->handle(paymentMethod: $paymentMethod, command: $command));
    }

    #[Test]
    public function it_throws_payment_validation_when_update_cc_fields_for_ach_payment_method(): void
    {
        $this->mockWorldPayGuzzleUpdatePayment();
        $this->createPaymentProcessorMock();

        $paymentMethod = PaymentMethod::factory()->ach()->makeWithRelationships(
            attributes: [
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'id' => Str::uuid(),
            ],
            relationships: [
                'account' => Account::factory()->makeWithRelationships(
                    relationships: [
                        'area' => Area::factory()->make()
                    ]
                )
            ]
        );

        $this->expectException(exception: PaymentValidationException::class);
        $this->expectExceptionMessage(message: __('messages.payment_method.update.cannot_update_cc_fields_on_ach'));

        $this->handler()->handle(paymentMethod: $paymentMethod, command: $this->command);
    }

    #[Test]
    #[DataProvider('notFoundPaymentAccountProvider')]
    public function it_throws_unprocessable_exception_when_could_not_find_payment_account_from_worldpay(array $input): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: $input['paymentMethodAttributes'] ?? []);

        $this->mockWorldPayGuzzleUpdatePayment(hasPaymentAccount: false);
        $this->createPaymentProcessorMock(authorizedResult: true);

        $this->expectException(UnprocessableContentException::class);
        $this->expectExceptionMessage(__('messages.payment_method.update.cannot_retrieve_payment_account'));

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->handler()->handle(paymentMethod: $paymentMethod, command: $input['command'] ?? $this->command);
    }

    #[Test]
    #[DataProvider('unprocessableEntityProvider')]
    public function it_throws_unprocessable_exception_when_third_party_process_fail(array $input, array $expected): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: array_merge([
            'id' => Str::uuid()->toString(),
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'payment_type_id' => PaymentTypeEnum::ACH->value,
        ], $input['paymentAttributes'] ?? []));
        $this->command = new UpdatePaymentMethodCommand(
            firstName: 'First name',
            lastName: 'Last name',
            addressLine1: 'Address line 1',
            addressLine2: null,
            email: 'exxample@goaptive.com',
            city: 'Utah',
            province: 'UT',
            postalCode: '01103',
            countryCode: 'US',
            creditCardExpirationMonth: null,
            creditCardExpirationYear: null,
            isPrimary: null
        );

        $this->mockWorldPayGuzzleUpdatePayment(isSuccess: $input['updateAccountResult']);
        $this->createPaymentProcessorMock(authorizedResult: $input['authorizeResult']);

        $this->expectException(exception: UnprocessableContentException::class);
        $this->expectExceptionMessage(message: sprintf($expected['message'], $paymentMethod->id));

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        Log::shouldReceive('debug');

        Log::shouldReceive('shareContext')->with([
            'payment_method_id' => $paymentMethod->id,
            'account_id' => $paymentMethod->account_id,
            'request' => $this->command->toArray(),
        ]);

        $this->handler()->handle(paymentMethod: $paymentMethod, command: $this->command);

        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_unprocessable_exception_when_update_fails(): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: ['payment_type_id' => PaymentTypeEnum::CC->value]);

        $this->paymentMethodRepository->method('save')->willThrowException(new \Exception('Connection Error'));

        $this->mockWorldPayGuzzleUpdatePayment();
        $this->createPaymentProcessorMock(authorizedResult: true);

        $this->expectException(exception: UnprocessableContentException::class);
        $this->expectExceptionMessage(message: 'Connection Error');

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        Log::shouldReceive('shareContext')->once()->with([
            'payment_method_id' => $paymentMethod->id,
            'account_id' => $paymentMethod->account_id,
            'request' => $this->command->toArray(),
        ]);

        $this->handler()->handle(paymentMethod: $paymentMethod, command: $this->command);
    }

    #[Test]
    #[DataProvider('successfulProvider')]
    public function it_returns_true_in_successful_scenario(array $input): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: array_merge([
            'id' => Str::uuid()->toString(),
        ], $input['paymentMethodAttributes'] ?? []));

        $this->paymentMethodRepository->method('save')->willReturn($paymentMethod);

        $payment = Payment::factory()->makeWithRelationships(attributes: [
            'id' => Str::uuid(),
            'payment_status_id' => PaymentStatusEnum::AUTHORIZING,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY,
            'payment_type_id' => $paymentMethod->payment_type_id,
        ], relationships: ['paymentMethod' => $paymentMethod]);
        $this->paymentRepository->method('update')->willReturn($payment);
        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());

        $this->mockWorldPayGuzzleUpdatePayment();
        $this->createPaymentProcessorMock(authorizedResult: true);

        $this->assertTrue($this->handler()->handle(paymentMethod: $paymentMethod, command: $input['command'] ?? $this->command));
    }

    public static function unprocessableEntityProvider(): \Iterator
    {
        yield 'Worldpay exception when validate payment' => [
            'input' => [
                'pestRoutesResponse' => 123,
                'authorizeResult' => false,
                'updateAccountResult' => true,
                'paymentAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                ],
            ],
            'expected' => [
                'message' => 'Payment Method %s is invalid',
            ],
        ];
        yield 'Worldpay exception when updating account' => [
            'input' => [
                'pestRoutesResponse' => 123,
                'authorizeResult' => true,
                'updateAccountResult' => false,
                'paymentAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                ],
            ],
            'expected' => [
                'message' => static fn () => __('messages.payment_method.update.cannot_update_payment_account', ['error' => 'Invalid PaymentAccountID']),
            ],
        ];
    }

    public static function notFoundPaymentAccountProvider(): \Iterator
    {
        yield 'contain ext but not found' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'cc_token' => null,
                    'external_ref_id' => random_int(1000, 10000),
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                ],
                'paymentAccountId' => null,
            ],
        ];
    }

    public static function successfulProvider(): \Iterator
    {
        yield 'CC' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                    'cc_token' => Str::uuid()->toString(),
                    'external_ref_id' => random_int(1000, 10000),
                ],
            ],
        ];
        yield 'ach' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::ACH->value,
                ],
                'command' => new UpdatePaymentMethodCommand(
                    firstName: 'First name',
                    lastName: 'Last name',
                    addressLine1: 'Address line 1',
                    addressLine2: null,
                    email: 'exxample@goaptive.com',
                    city: 'Utah',
                    province: 'UT',
                    postalCode: '01103',
                    countryCode: 'US',
                    creditCardExpirationMonth: null,
                    creditCardExpirationYear: null,
                    isPrimary: null
                ),
            ],
        ];
    }

    private function createPaymentMethod(array $attributes = []): PaymentMethod
    {
        $paymentMethod = PaymentMethod::factory()->cc()->makeWithRelationships(
            attributes: array_merge([
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'id' => Str::uuid()->toString(),
            ], $attributes),
            relationships: [
                'account' => Account::factory()->makeWithRelationships(
                    relationships: [
                        'area' => Area::factory()->make()
                    ]
                ),
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::CC->value
                ])
            ]
        );
        return $paymentMethod;
    }

    private function createPaymentProcessorMock(bool $authorizedResult = true): void
    {
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $this->paymentProcessor->method('authorize')->willReturn(value: $authorizedResult);
    }

    private function mockWorldPayGuzzleUpdatePayment(bool $isSuccess = true, bool $hasPaymentAccount = true): void
    {
        /** @var GuzzleClient|MockObject $guzzle */
        $guzzle = $this->createMock(GuzzleClient::class);
        $guzzle->method('post')->willReturnOnConsecutiveCalls(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $hasPaymentAccount ? WorldpayResponseStub::getPaymentAccountSuccess() : WorldpayResponseStub::getPaymentAccountUnsuccess(),
            ),
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $isSuccess ? WorldpayResponseStub::updatePaymentAccountSuccess() : WorldpayResponseStub::updatePaymentAccountUnsuccess(),
            )
        );

        $this->app->instance(abstract: GuzzleClient::class, instance: $guzzle);
    }

    private function handler(
        PaymentProcessor|null $paymentProcessor = null,
        PaymentMethodRepository|null $paymentMethodRepository = null,
        PaymentRepository|null $paymentRepository = null,
    ): UpdatePaymentMethodHandler {
        return new UpdatePaymentMethodHandler(
            paymentProcessor: $paymentProcessor ?? $this->paymentProcessor,
            paymentMethodRepository: $paymentMethodRepository ?? $this->paymentMethodRepository,
            paymentRepository: $paymentRepository ?? $this->paymentRepository,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->paymentProcessor,
            $this->paymentMethodRepository,
            $this->paymentRepository,
            $this->command
        );
    }
}
