<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Controllers;

use App\Api\Controllers\ProcessScheduledPaymentsController;
use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Requests\ProcessScheduledPaymentsRequest;
use App\Jobs\ScheduledPayment\RetrieveAreaScheduledPaymentsJob;
use Aptive\Component\Http\Exceptions\BadRequestHttpException;
use ConfigCat\ClientInterface;
use ConfigCat\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\UnitTestCase;

class ProcessScheduledPaymentsControllerTest extends UnitTestCase
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
    public function it_returns_json_success_response_for_process_scheduled_payments_method_with_area_ids_and_dispatch_jobs(): void
    {
        Queue::fake(jobsToFake: RetrieveAreaScheduledPaymentsJob::class);

        $this->mockAreaRepository->method('retrieveAllIds')->willReturn([1, 2, 3, 47, 49]);

        $data = ['area_ids' => [47, 49]];

        $request = new ProcessScheduledPaymentsRequest();
        $request->initialize($data);

        $response = $this->controller()(request: $request);

        Queue::assertPushed(job: RetrieveAreaScheduledPaymentsJob::class, callback: count($data['area_ids']));
        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    #[Test]
    public function it_throws_bad_request_exception_if_incorrect_area_id_passed(): void
    {
        Queue::fake(jobsToFake: RetrieveAreaScheduledPaymentsJob::class);

        $validAreaId = 49;
        $incorrectAreaId = 9999999999;

        $request = new ProcessScheduledPaymentsRequest();
        $request->initialize(['area_ids' => [$validAreaId, $incorrectAreaId]]);

        $this->mockAreaRepository->method('retrieveAllIds')->willReturn([$validAreaId]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(__('messages.payment.scheduled_payments_processing.invalid_area_id', ['ids' => $incorrectAreaId]));

        $this->controller()(request: $request);

        Queue::assertNotPushed(job: RetrieveAreaScheduledPaymentsJob::class);
    }

    #[Test]
    public function it_returns_json_success_response_for_process_scheduled_payments_without_area_ids_and_dispatch_several_jobs(): void
    {
        Queue::fake(jobsToFake: RetrieveAreaScheduledPaymentsJob::class);

        $request = new ProcessScheduledPaymentsRequest();
        $request->initialize();

        $areaIds = [1, 2, 3, 4];
        $this->mockAreaRepository->method('retrieveAllIds')->willReturn($areaIds);

        $response = $this->controller()(request: $request);

        Queue::assertPushed(job: RetrieveAreaScheduledPaymentsJob::class, callback: count($areaIds));
        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    #[Test]
    public function it_dispatches_jobs_only_for_areas_that_are_allowed_by_feature_flag_value_and_add_logs_when_running_without_parameters(): void
    {
        Queue::fake(jobsToFake: RetrieveAreaScheduledPaymentsJob::class);

        $request = new ProcessScheduledPaymentsRequest();
        $request->initialize();

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

        Log::shouldReceive('warning')->once()->with(__('messages.payment.scheduled_payments_processing.disabled', ['id' => 2]));
        Log::shouldReceive('warning')->once()->with(__('messages.payment.scheduled_payments_processing.disabled', ['id' => 4]));
        Log::shouldReceive('info')->once()->with(__('messages.payment.scheduled_payments_processing.initiated', ['id' => 1]));
        Log::shouldReceive('info')->once()->with(__('messages.payment.scheduled_payments_processing.initiated', ['id' => 3]));
        Log::shouldReceive('info')->once()->with(__('messages.payment.scheduled_payments_processing.initiated', ['id' => 5]));

        $response = $this->controller()(request: $request);

        Queue::assertPushed(job: RetrieveAreaScheduledPaymentsJob::class, callback: count($allowedAreaIds));

        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    #[Test]
    public function it_dispatches_jobs_only_for_areas_that_are_allowed_by_feature_flag_value_and_add_warning_log_for_unallowed_areas_when_running_with_specific_offices(): void
    {
        Queue::fake(jobsToFake: RetrieveAreaScheduledPaymentsJob::class);

        $allAreaIds = [1, 2, 3, 4, 5, 6];
        $passedAreaIds = [1, 2, 4, 5];
        $allowedAreaIds = [1, 4];

        $this->mockAreaRepository->method('retrieveAllIds')->willReturn($allAreaIds);

        $request = new ProcessScheduledPaymentsRequest();
        $request->initialize(query: ['area_ids' => $passedAreaIds]);

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

        $response = $this->controller()(request: $request);

        Queue::assertPushed(job: RetrieveAreaScheduledPaymentsJob::class, callback: count(array_intersect($passedAreaIds, $allowedAreaIds)));

        $this->assertEquals(expected: Response::HTTP_ACCEPTED, actual: $response->getStatusCode());
    }

    private function controller(): ProcessScheduledPaymentsController
    {
        return new ProcessScheduledPaymentsController(
            areaRepository: $this->mockAreaRepository,
            configCatClient: $this->mockConfigCatClient
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->mockAreaRepository, $this->mockConfigCatClient);
    }
}
