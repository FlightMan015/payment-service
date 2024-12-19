<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Requests\ProcessScheduledPaymentsRequest;
use App\Api\Responses\AcceptedSuccessResponse;
use App\Jobs\ScheduledPayment\RetrieveAreaScheduledPaymentsJob;
use Aptive\Component\Http\Exceptions\BadRequestHttpException;
use ConfigCat\ClientInterface;
use ConfigCat\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

readonly class ProcessScheduledPaymentsController
{
    /**
     * @param AreaRepository $areaRepository
     * @param ClientInterface $configCatClient
     */
    public function __construct(private AreaRepository $areaRepository, private ClientInterface $configCatClient)
    {
    }

    /**
     * @param ProcessScheduledPaymentsRequest $request
     *
     * @throws BadRequestHttpException
     *
     * @return AcceptedSuccessResponse
     */
    public function __invoke(ProcessScheduledPaymentsRequest $request): AcceptedSuccessResponse
    {
        $this->validateAreaIds(areaIds: $areaIds = $request->area_ids);
        $this->dispatchRetrieveAreaScheduledPaymentsJobs(areaIds: $areaIds);

        return AcceptedSuccessResponse::create(
            message: __('messages.payment.scheduled_payments_processing.started'),
            selfLink: $request->fullUrl()
        );
    }

    /**
     * @param int[]|null $areaIds
     *
     * @throws BadRequestHttpException
     */
    private function validateAreaIds(array|null $areaIds): void
    {
        if (empty($areaIds)) {
            return; // null and empty array are acceptable values
        }

        $allAreaIds = $this->areaRepository->retrieveAllIds();
        if ($diff = array_diff($areaIds, $allAreaIds)) {
            throw new BadRequestHttpException(
                message: __(
                    'messages.payment.scheduled_payments_processing.invalid_area_id',
                    ['ids' => implode(', ', $diff)]
                )
            );
        }
    }

    private function dispatchRetrieveAreaScheduledPaymentsJobs(array|null $areaIds): void
    {
        $areaIds = !empty($areaIds) ? $areaIds : $this->areaRepository->retrieveAllIds();

        foreach ($areaIds as $areaId) {
            if (!$this->isScheduledPaymentsProcessingEnabled(areaId: $areaId)) {
                Log::warning(message: __('messages.payment.scheduled_payments_processing.disabled', ['id' => $areaId]));
                continue;
            }

            RetrieveAreaScheduledPaymentsJob::dispatch($areaId);
            Log::info(message: __('messages.payment.scheduled_payments_processing.initiated', ['id' => $areaId]));
        }
    }

    private function isScheduledPaymentsProcessingEnabled(int $areaId): bool
    {
        $user = new User(
            identifier: Str::uuid()->toString(),
            custom: [
                'area_id' => (string)$areaId,
            ]
        );

        return $this->configCatClient->getValue(
            key: 'isScheduledPaymentsProcessingEnabled',
            defaultValue: false,
            user: $user
        );
    }
}
