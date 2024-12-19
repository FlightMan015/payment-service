<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Helpers\JsonDecoder;
use App\Jobs\WorldpayPaymentMethodUpdateExpirationDataJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\Unit\UnitTestCase;

class WorldpayPaymentMethodUpdateExpirationDataJobTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWorldPayCredentialsRepository();
    }

    #[Test]
    public function it_updates_expiration_data_if_worldpay_returns_payment_profile_information(): void
    {
        $paymentMethod = $this->makePaymentMethod();

        $response = WorldpayResponseStub::getPaymentAccountSuccess(withExpirationData: true);
        $parsedResponse = (object)array_pop(JsonDecoder::decode(JsonDecoder::encode(simplexml_load_string($response)))['Response']['QueryData']['Items']);

        $expectedExpirationMonth = (int)$parsedResponse->ExpirationMonth;
        $expectedExpirationYear = (int)Carbon::createFromFormat(format: 'y', time: $parsedResponse->ExpirationYear)->format(format: 'Y');

        $this->mockWorldPayGuzzleGetPaymentAccount(response: $response);

        $paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);

        $this->assertWorldpayRequestLogs();

        Log::expects('info')->with(
            __('messages.worldpay.populate_expiration_data.start_updating'),
            ['payment_method_id' => $paymentMethod->id]
        );

        Log::expects('info')->with(
            __('messages.worldpay.populate_expiration_data.updated'),
            ['payment_method_id' => $paymentMethod->id, 'cc_expiration_month' => $expectedExpirationMonth, 'cc_expiration_year' => $expectedExpirationYear]
        );

        $job = new WorldpayPaymentMethodUpdateExpirationDataJob(paymentMethod: $paymentMethod);

        $paymentMethodRepository->expects($this->once())->method('update')
            ->with(
                $paymentMethod,
                $this->callback(
                    static fn (array $attributes) =>
                        $attributes['cc_expiration_month'] === $expectedExpirationMonth
                        && $attributes['cc_expiration_year'] === $expectedExpirationYear
                )
            )
            ->willReturnCallback(static function () use ($paymentMethod, $expectedExpirationMonth, $expectedExpirationYear) {
                $paymentMethod->cc_expiration_month = $expectedExpirationMonth;
                $paymentMethod->cc_expiration_year = $expectedExpirationYear;

                return $paymentMethod;
            });

        $job->handle(paymentMethodRepository: $paymentMethodRepository);
    }

    #[Test]
    public function it_adds_warning_log_and_does_not_update_payment_method_if_payment_account_not_found_in_gateway(): void
    {
        $paymentMethod = $this->makePaymentMethod();

        $this->mockWorldPayGuzzleGetPaymentAccount(response: WorldpayResponseStub::getPaymentAccountUnsuccess());

        $paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);

        $this->assertWorldpayRequestLogs();

        Log::expects('info')->with(
            __('messages.worldpay.populate_expiration_data.start_updating'),
            ['payment_method_id' => $paymentMethod->id]
        );

        Log::expects('warning')->with(
            __('messages.worldpay.populate_expiration_data.account_not_found'),
            ['payment_method_id' => $paymentMethod->id]
        );

        $job = new WorldpayPaymentMethodUpdateExpirationDataJob(paymentMethod: $paymentMethod);

        $paymentMethodRepository->expects($this->never())->method('update');

        $job->handle(paymentMethodRepository: $paymentMethodRepository);
    }

    #[Test]
    public function it_adds_warning_log_and_does_not_update_payment_method_if_payment_account_was_found_but_it_does_not_have_expiration_data(): void
    {
        $paymentMethod = $this->makePaymentMethod();

        $this->mockWorldPayGuzzleGetPaymentAccount(response: WorldpayResponseStub::getPaymentAccountSuccess());

        $paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);

        $this->assertWorldpayRequestLogs();

        Log::expects('info')->with(
            __('messages.worldpay.populate_expiration_data.start_updating'),
            ['payment_method_id' => $paymentMethod->id]
        );

        Log::expects('warning')->with(__(
            'messages.worldpay.populate_expiration_data.account_validation_failed',
            ['message' => __('messages.worldpay.populate_expiration_data.expiration_data_missing')]
        ));

        $job = new WorldpayPaymentMethodUpdateExpirationDataJob(paymentMethod: $paymentMethod);

        $paymentMethodRepository->expects($this->never())->method('update');

        $job->handle(paymentMethodRepository: $paymentMethodRepository);
    }

    #[Test]
    public function it_adds_warning_log_and_does_not_update_payment_method_if_payment_account_was_found_but_it_has_empty_expiration_data(): void
    {
        $paymentMethod = $this->makePaymentMethod();

        $this->mockWorldPayGuzzleGetPaymentAccount(response: WorldpayResponseStub::getPaymentAccountSuccess(withEmptyExpirationData: true));

        $paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);

        $this->assertWorldpayRequestLogs();

        Log::expects('info')->with(
            __('messages.worldpay.populate_expiration_data.start_updating'),
            ['payment_method_id' => $paymentMethod->id]
        );

        Log::expects('warning')->with(__(
            'messages.worldpay.populate_expiration_data.account_validation_failed',
            ['message' => __('messages.worldpay.populate_expiration_data.expiration_data_empty')]
        ));

        $job = new WorldpayPaymentMethodUpdateExpirationDataJob(paymentMethod: $paymentMethod);

        $paymentMethodRepository->expects($this->never())->method('update');

        $job->handle(paymentMethodRepository: $paymentMethodRepository);
    }

    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        Queue::fake();

        $paymentMethod = $this->makePaymentMethod();
        WorldpayPaymentMethodUpdateExpirationDataJob::dispatch($paymentMethod);

        Queue::assertPushedOn(queue: config(key: 'queue.connections.sqs.queues.process_payments'), job: WorldpayPaymentMethodUpdateExpirationDataJob::class);
    }

    private function mockWorldPayGuzzleGetPaymentAccount(string $response): void
    {
        /** @var GuzzleClient|MockObject $guzzle */
        $guzzle = $this->createMock(GuzzleClient::class);
        $guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $response
            ),
        );

        $this->app->instance(abstract: GuzzleClient::class, instance: $guzzle);
    }

    private function makePaymentMethod(): PaymentMethod
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'cc_expiration_month' => null,
                'cc_expiration_year' => null
            ],
            relationships: ['account' => $account]
        );

        return $paymentMethod;
    }

    private function assertWorldpayRequestLogs(): void
    {
        Log::expects('debug')->once()->with(__('messages.worldpay.payment_account_query.start'));
        Log::expects('debug')->once()->withSomeOfArgs(__('messages.worldpay.send_request'));
        Log::expects('debug')->once()->with(__('messages.response.http_code', ['code' => 200]));
        Log::expects('debug')->once()->with($this->stringStartsWith('Response:'));
        Log::expects('debug')->once()->with(__('messages.worldpay.completed', ['method' => 'PaymentAccountQuery']));
    }
}
