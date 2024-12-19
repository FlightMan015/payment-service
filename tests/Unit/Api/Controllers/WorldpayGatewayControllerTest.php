<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Controllers;

use App\Api\Commands\PopulateWorldpayExpirationDataHandler;
use App\Api\Controllers\WorldpayGatewayController;
use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Requests\PostAchPaymentStatusRequest;
use App\Jobs\RetrieveUnsettledPaymentsJob;
use ConfigCat\ClientInterface;
use ConfigCat\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class WorldpayGatewayControllerTest extends UnitTestCase
{
    /** @var MockObject&AreaRepository $mockAreaRepository */
    private AreaRepository $mockAreaRepository;
    /** @var MockObject&ClientInterface $mockConfigCatClient */
    private ClientInterface $mockConfigCatClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAreaRepository = $this->createMock(originalClassName: AreaRepository::class);
        $this->mockConfigCatClient = $this->createMock(originalClassName: ClientInterface::class);
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
    }

    #[Test]
    public function populate_expiration_data_method_calls_handler_and_returns_accepted_response(): void
    {
        $handler = $this->createMock(PopulateWorldpayExpirationDataHandler::class);
        $handler->expects($this->once())->method('handle');

        $controller = new WorldpayGatewayController($this->mockAreaRepository, $this->mockConfigCatClient);
        $response = $controller->populateExpirationData($handler);

        $this->assertSame(__('messages.worldpay.populate_expiration_data.start_process'), $response->getData()->result->message);
        $this->assertSame(route(name: 'gateways.tokenex.update-accounts'), $response->getData()->_metadata->links->self);
    }

    #[Test]
    public function it_returns_json_success_response_for_check_ach_status_with_area_ids_and_dispatch_jobs(): void
    {
        Queue::fake(jobsToFake: RetrieveUnsettledPaymentsJob::class);

        $this->mockAreaRepository->method('retrieveAllIds')->willReturn([1, 2, 3, 47, 49]);

        $data = [
            'area_ids' => [47, 49],
            'processed_at_from' => now()->subDays(1),
            'processed_at_to' => now(),
        ];

        $request = new PostAchPaymentStatusRequest();
        $request->initialize($data);

        $response = $this->controller()->checkAchStatus(request: $request);

        Queue::assertPushed(job: RetrieveUnsettledPaymentsJob::class, callback: count($data['area_ids']));
        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    #[Test]
    public function it_returns_json_success_response_for_check_ach_status_without_area_ids_and_dispatch_several_jobs(): void
    {
        Queue::fake(jobsToFake: RetrieveUnsettledPaymentsJob::class);

        $request = new PostAchPaymentStatusRequest();
        $request->initialize([
            'processed_at_from' => now()->subDays(1),
            'processed_at_to' => now(),
        ]);

        $areaIds = [1, 2, 3, 4];
        $this->mockAreaRepository->method('retrieveAllIds')->willReturn($areaIds);

        $response = $this->controller()->checkAchStatus(request: $request);

        Queue::assertPushed(job: RetrieveUnsettledPaymentsJob::class, callback: count($areaIds));
        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    #[Test]
    public function it_dispatches_jobs_only_for_areas_that_are_allowed_by_feature_flag_value_and_add_logs_when_running_without_parameters(): void
    {
        Queue::fake(jobsToFake: RetrieveUnsettledPaymentsJob::class);

        $request = new PostAchPaymentStatusRequest();
        $request->initialize([
            'processed_at_from' => now()->subDays(1),
            'processed_at_to' => now(),
        ]);

        $areaIds = [1, 2, 3, 4, 5];
        $allowedAreaIds = [1, 3, 5];

        $this->mockAreaRepository->method('retrieveAllIds')->willReturn($areaIds);

        $this->mockConfigCatClient = $this->createMock(originalClassName: ClientInterface::class);
        $this->mockConfigCatClient
            ->method('getValue')
            ->willReturnCallback(static function (string $key, bool $defaultValue, User $user) use ($allowedAreaIds) {
                if (in_array(needle: $user->getAttribute('area_id'), haystack: $allowedAreaIds, strict: false)) {
                    return true;
                }

                return false;
            });

        Log::shouldReceive('warning')->times(count($areaIds) - count($allowedAreaIds));
        Log::shouldReceive('info')->times(count($allowedAreaIds));

        $response = $this->controller()->checkAchStatus(request: $request);

        Queue::assertPushed(job: RetrieveUnsettledPaymentsJob::class, callback: count($allowedAreaIds));

        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    #[Test]
    public function it_dispatches_jobs_only_for_areas_that_are_allowed_by_feature_flag_value_and_add_warning_log_for_unallowed_areas_when_running_with_specific_offices(): void
    {
        Queue::fake(jobsToFake: RetrieveUnsettledPaymentsJob::class);

        $allAreaIds = [1, 2, 3, 4, 5, 6];
        $passedAreaIds = [1, 2, 4, 5];
        $allowedAreaIds = [1, 4];

        $this->mockAreaRepository->method('retrieveAllIds')->willReturn($allAreaIds);

        $request = new PostAchPaymentStatusRequest();
        $request->initialize(query: [
            'area_ids' => $passedAreaIds,
            'processed_at_from' => now()->subDays(1),
            'processed_at_to' => now(),
        ]);

        $this->mockConfigCatClient = $this->createMock(originalClassName: ClientInterface::class);
        $this->mockConfigCatClient
            ->method('getValue')
            ->willReturnCallback(static function (string $key, bool $defaultValue, User $user) use ($allowedAreaIds) {
                if (in_array(needle: $user->getAttribute('area_id'), haystack: $allowedAreaIds, strict: false)) {
                    return true;
                }

                return false;
            });

        Log::shouldReceive('warning')->times(count(array_diff($passedAreaIds, $allowedAreaIds)));
        Log::shouldReceive('info')->times(count(array_intersect($passedAreaIds, $allowedAreaIds)));

        $response = $this->controller()->checkAchStatus(request: $request);

        Queue::assertPushed(job: RetrieveUnsettledPaymentsJob::class, callback: count(array_intersect($passedAreaIds, $allowedAreaIds)));

        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    private function controller(): WorldpayGatewayController
    {
        return new WorldpayGatewayController(
            areaRepository: $this->mockAreaRepository,
            configCatClient: $this->mockConfigCatClient
        );
    }
}
