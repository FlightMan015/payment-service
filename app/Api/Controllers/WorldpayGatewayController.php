<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\PopulateWorldpayExpirationDataHandler;
use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Requests\PostAchPaymentStatusRequest;
use App\Api\Responses\AcceptedSuccessResponse;
use App\Jobs\RetrieveUnsettledPaymentsJob;
use ConfigCat\ClientInterface;
use ConfigCat\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorldpayGatewayController
{
    /**
     * @param AreaRepository $areaRepository
     * @param ClientInterface $configCatClient
     */
    public function __construct(
        private AreaRepository $areaRepository,
        private ClientInterface $configCatClient
    ) {
    }

    /**
     * @param PopulateWorldpayExpirationDataHandler $handler
     *
     * @return AcceptedSuccessResponse
     */
    public function populateExpirationData(PopulateWorldpayExpirationDataHandler $handler): AcceptedSuccessResponse
    {
        $handler->handle();

        return AcceptedSuccessResponse::create(
            message: __('messages.worldpay.populate_expiration_data.start_process'),
            selfLink: route(name: 'gateways.tokenex.update-accounts')
        );
    }

    /**
     * @param PostAchPaymentStatusRequest $request
     *
     * @return AcceptedSuccessResponse
     */
    public function checkAchStatus(
        PostAchPaymentStatusRequest $request,
    ): AcceptedSuccessResponse {
        $this->dispatchRetrieveUnsettledPaymentsJob($request);

        return AcceptedSuccessResponse::create(
            message: __('messages.payment.ach_status_checking.initiated', [
                'from' => $request->get('processed_at_from'),
                'to' => $request->get('processed_at_to'),
            ]),
            selfLink: $request->fullUrl(),
        );
    }

    private function dispatchRetrieveUnsettledPaymentsJob(PostAchPaymentStatusRequest $request): void
    {
        $areaIds = $request->has('area_ids') ? $request->area_ids : $this->areaRepository->retrieveAllIds();

        foreach ($areaIds as $areaId) {
            if (!$this->isAchStatusCheckingEnabled(areaId: $areaId)) {
                Log::warning(message: __('messages.payment.ach_status_checking.disabled_for_area', [
                    'id' => $areaId,
                ]));
                continue;
            }

            RetrieveUnsettledPaymentsJob::dispatch(
                $request->date('processed_at_from'),
                $request->date('processed_at_to'),
                $areaId,
            );
            Log::info(message: __('messages.payment.ach_status_checking.initiated_for_area', [
                'from' => $request->get('processed_at_from'),
                'to' => $request->get('processed_at_to'),
                'id' => $areaId,
            ]));
        }
    }

    private function isAchStatusCheckingEnabled(int $areaId): bool
    {
        $user = new User(
            identifier: Str::uuid()->toString(),
            custom: [
                'area_id' => (string)$areaId,
            ]
        );

        return $this->configCatClient->getValue(key: 'isAchStatusCheckingEnabled', defaultValue: false, user: $user);
    }
}
