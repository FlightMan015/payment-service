<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Customer\Api\Controllers;

use App\Constants\HttpHeader;
use Customer\Api\Commands\CreatePaymentHandler;
use Customer\Api\Controllers\PaymentController;
use Customer\Api\Exceptions\PestroutesAPIException;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\UnitTestCase;

class PaymentControllerTest extends UnitTestCase
{
    private PaymentController $controller;
    /** @var MockInterface&CreatePaymentHandler */
    private CreatePaymentHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = Mockery::mock(CreatePaymentHandler::class);
        $this->controller = new PaymentController();
    }

    #[Test]
    public function it_translates_any_other_pestroutes_sdk_exception_to_500_response(): void
    {
        $data = [
            'payoff_outstanding_balance' => true
        ];

        $request = Request::create(route('createPayment', 12), 'POST', $data);
        $request->headers->set(HttpHeader::APTIVE_PESTROUTES_OFFICE_ID, '1');

        $this->mockHandler->expects('handle')
            ->withAnyArgs()
            ->andThrow(new PestroutesAPIException('Unsuccessful Response: Some error'));

        $response = $this->controller->create($request, 8332, $this->mockHandler);

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_expected_200_response_when_successfully_creates_payment(): void
    {
        $data = [
            'payoff_outstanding_balance' => true
        ];

        $request = Request::create(route('createPayment', 12), 'POST', $data);
        $request->headers->set(HttpHeader::APTIVE_PESTROUTES_OFFICE_ID, '1');

        $this->mockHandler->expects('handle')
            ->withAnyArgs()
            ->andReturns(true);

        $response = $this->controller->create($request, 8332, $this->mockHandler);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->controller, $this->mockHandler, $this->mockAreaRepository);
    }
}
