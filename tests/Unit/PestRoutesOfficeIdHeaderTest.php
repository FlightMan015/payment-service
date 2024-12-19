<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\HttpHeader;
use App\Http\Middleware\PestRoutesOfficeIdHeader;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class PestRoutesOfficeIdHeaderTest extends UnitTestCase
{
    #[Test]
    public function it_return_error_if_office_id_is_empty_from_middleware(): void
    {
        $request = Request::create('/api/v1/entity/12/sub');

        $next = static function (Request $request) use (&$called) {
            $called = true;
        };

        $middleware = new PestRoutesOfficeIdHeader();
        $response = $middleware->handle($request, $next);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('{"errors":{"status":400,"title":"Office id is required"}}', $response->getContent());
    }

    #[Test]
    public function it_return_error_if_office_id_is_not_a_number_from_middleware(): void
    {
        $request = Request::create('/api/v1/entity/12/sub');
        $request->headers->set(HttpHeader::APTIVE_PESTROUTES_OFFICE_ID, 'abc');

        $next = static function (Request $request) use (&$called) {
            $called = true;
        };

        $middleware = new PestRoutesOfficeIdHeader();
        $response = $middleware->handle($request, $next);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('{"errors":{"status":400,"title":"Office id is not integer"}}', $response->getContent());
    }

    #[Test]
    public function it_pass_middleware(): void
    {
        $request = Request::create('/api/v1/entity/12/sub');
        $request->headers->set(HttpHeader::APTIVE_PESTROUTES_OFFICE_ID, '1');

        $next = static function (Request $request) use (&$called) {
            $called = true;
        };

        $middleware = new PestRoutesOfficeIdHeader();
        $middleware->handle($request, $next);

        $this->assertTrue(true);
    }
}
