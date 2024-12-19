<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Controllers;

use App\Api\Commands\CreatePaymentHandler;
use App\Api\Controllers\PaymentController;
use App\Api\Requests\PostPaymentRequest;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\UnitTestCase;

class PaymentControllerTest extends UnitTestCase
{
    private PaymentController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PaymentController();
    }

    #[Test]
    public function it_returns_expected_201_response_when_successfully_creates_payment(): void
    {
        $data = [
            'account_id' => Str::uuid()->toString(),
            'amount' => 100,
            'type' => PaymentTypeEnum::CHECK->name,
            'check_date' => '2021-01-01'
        ];

        $request = PostPaymentRequest::create(route('payments.create'), 'POST', $data);

        $handlerMock = $this->createMock(originalClassName: CreatePaymentHandler::class);
        $handlerMock->method('handle')->willReturn(Str::uuid()->toString());
        $response = $this->controller->create($request, $handlerMock);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->controller);
    }
}
