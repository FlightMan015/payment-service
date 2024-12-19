<?php

declare(strict_types=1);

namespace Tests\Contract\Customer;

use App\Constants\HttpHeader;
use Aptive\Component\Http\Exceptions\NotFoundHttpException as ExceptionsNotFoundHttpException;
use Aptive\Component\Http\HttpStatus;
use Customer\Api\Commands\CreatePaymentHandler;
use Customer\Api\Controllers\PaymentController;
use Customer\Api\Exceptions\AutoPayStatusException;
use Customer\Api\Exceptions\InvalidPaymentHoldDateException;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreatePaymentEndpointTest extends TestCase
{
    // TODO: rework this test to fake http requests not mock handler
    #[Test]
    public function it_returns_expected_500_response_for_generic_errors(): void
    {
        $mockHandler = Mockery::mock(CreatePaymentHandler::class);
        $mockHandler->allows('handle')->andThrow(new \Exception('Some random error'));

        $this->app->instance(CreatePaymentHandler::class, $mockHandler);

        $data = [
            'payoff_outstanding_balance' => true
        ];

        $response = $this->postJson(
            route('createPayment', random_int(1, 100000)),
            $data,
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::INTERNAL_SERVER_ERROR);
    }

    #[Test]
    public function it_returns_expected_400_response_when_amount_provided(): void
    {
        $data = [
            'payoff_outstanding_balance' => true,
            'amount' => 10.0
        ];

        $response = $this->postJson(
            route('createPayment', random_int(1, 100000)),
            $data,
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::BAD_REQUEST);
    }

    #[Test]
    public function it_returns_expected_404_response_for_not_found_exceptions(): void
    {
        $mockHandler = Mockery::mock(CreatePaymentHandler::class);
        $mockHandler->expects('handle')
            ->withAnyArgs()
            ->andThrow(new ExceptionsNotFoundHttpException('Something not found'));

        $this->app->instance(CreatePaymentHandler::class, $mockHandler);

        $data = [
            'payoff_outstanding_balance' => true
        ];

        $response = $this->postJson(
            route('createPayment', random_int(1, 100000)),
            $data,
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::NOT_FOUND);
    }

    #[Test]
    public function it_creates_payment_and_returns_200(): void
    {
        $mockHandler = Mockery::mock(CreatePaymentHandler::class);
        $mockHandler->expects('handle')
            ->withAnyArgs()
            ->andReturns(true);

        $this->app->instance(CreatePaymentHandler::class, $mockHandler);

        $data = [
            'payoff_outstanding_balance' => true
        ];

        $response = $this->postJson(
            route('createPayment', random_int(1, 100000)),
            $data,
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::OK);
    }

    #[Test]
    public function it_returns_422_response_on_invalid_payment_hold_date(): void
    {
        $mockHandler = Mockery::mock(CreatePaymentHandler::class);
        $mockHandler->allows('handle')->andThrow(InvalidPaymentHoldDateException::class);

        $this->app->instance(CreatePaymentHandler::class, $mockHandler);

        $data = [
            'payoff_outstanding_balance' => true
        ];

        $response = $this->postJson(
            route('createPayment', random_int(1, 100000)),
            $data,
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function it_returns_422_response_on_invalid_autopay_status(): void
    {
        $mockHandler = Mockery::mock(CreatePaymentHandler::class);
        $mockHandler->allows('handle')->andThrow(AutoPayStatusException::class);

        $this->app->instance(CreatePaymentHandler::class, $mockHandler);

        $data = [
            'payoff_outstanding_balance' => true
        ];

        $response = $this->postJson(
            route('createPayment', random_int(1, 100000)),
            $data,
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function it_returns_429_too_many_requests_if_lock_for_customer_payment_creation_exists(): void
    {
        $customerId = random_int(1, 100000);

        $lock = Cache::lock(name: sprintf('%d:payment:create', $customerId), seconds: PaymentController::AQUIRED_LOCK_SECONDS);
        $lock->get();

        $response = $this->postJson(
            route('createPayment', $customerId),
            ['payoff_outstanding_balance' => true],
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::TOO_MANY_REQUESTS);

        $lock->release();
    }

    #[Test]
    public function it_returns_500_when_body_is_not_correct(): void
    {
        $data = ['key' => 'value'];

        $response = $this->postJson(
            route('createPayment', random_int(1, 100000)),
            $data,
            [HttpHeader::APTIVE_PESTROUTES_OFFICE_ID => 1]
        );

        $response->assertStatus(HttpStatus::INTERNAL_SERVER_ERROR);
    }
}
