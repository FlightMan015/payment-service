<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\PaymentSyncReportHandler;
use App\Api\Responses\PaymentSyncReportSuccessResponse;
use Illuminate\Routing\Controller;

class ProcessPaymentsSyncReportController extends Controller
{
    /**
     * @param PaymentSyncReportHandler $handler
     *
     * @return PaymentSyncReportSuccessResponse
     */
    public function __invoke(PaymentSyncReportHandler $handler): PaymentSyncReportSuccessResponse
    {
        $report = $handler->handle();

        return PaymentSyncReportSuccessResponse::create(message: $report->message);
    }

}
