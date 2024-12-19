<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Helpers\ArrayHelper;
use App\Models\Transaction;

trait ExtractReasonFromTransactionTrait
{
    private function extractReasonFromReturnTransaction(Transaction $transaction): string
    {
        $parsedResponse = ArrayHelper::parseGatewayResponseXmlToArray(
            rawResponseLog: $transaction->raw_response_log
        );

        return data_get($parsedResponse, 'Response.ReportingData.Items.Item.TransactionStatus', '');
    }
}
