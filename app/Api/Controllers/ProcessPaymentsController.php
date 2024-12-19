<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Requests\ProcessPaymentsRequest;
use App\Api\Responses\AcceptedSuccessResponse;
use App\Api\Traits\ValidatesPaymentProcessingConfig;
use App\Jobs\RetrieveAreaUnpaidInvoicesJob;
use Aptive\Component\Http\Exceptions\BadRequestHttpException;
use ConfigCat\ClientInterface;
use ConfigCat\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

readonly class ProcessPaymentsController
{
    use ValidatesPaymentProcessingConfig;

    /**
     * @param AreaRepository $areaRepository
     * @param array $config
     * @param ClientInterface $configCatClient
     */
    public function __construct(
        private AreaRepository $areaRepository,
        private array $config,
        private ClientInterface $configCatClient
    ) {
        $this->validateConfig();
    }

    /**
     * @param ProcessPaymentsRequest $request
     *
     * @throws BadRequestHttpException
     *
     * @return AcceptedSuccessResponse
     */
    public function __invoke(ProcessPaymentsRequest $request): AcceptedSuccessResponse
    {
        $this->validateAreaIds(areaIds: $areaIds = $request->area_ids);
        $this->dispatchRetrieveAreaUnpaidInvoicesJobs(areaIds: $areaIds);

        return AcceptedSuccessResponse::create(message: __('messages.payment.batch_processing.started'), selfLink: $request->fullUrl());
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
            throw new BadRequestHttpException(message: __('messages.payment.batch_processing.invalid_area_id', ['ids' => implode(', ', $diff)]));
        }
    }

    private function dispatchRetrieveAreaUnpaidInvoicesJobs(array|null $areaIds): void
    {
        $areaIds = !empty($areaIds) ? $areaIds : $this->areaRepository->retrieveAllIds();

        foreach ($areaIds as $areaId) {
            if (!$this->isBatchPaymentProcessingEnabled(areaId: $areaId)) {
                Log::warning(message: __('messages.payment.batch_processing.disabled', ['id' => $areaId]));
                continue;
            }

            RetrieveAreaUnpaidInvoicesJob::dispatch($areaId, $this->config);
            Log::info(message: __('messages.payment.batch_processing.initiated', ['id' => $areaId]));
        }
    }

    private function isBatchPaymentProcessingEnabled(int $areaId): bool
    {
        $user = new User(
            identifier: Str::uuid()->toString(),
            custom: [
                'area_id' => (string)$areaId,
            ]
        );

        return $this->configCatClient->getValue(key: 'isBatchPaymentProcessingEnabled', defaultValue: false, user: $user);
    }
}
