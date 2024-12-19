<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Helpers\DateTimeHelper;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;

trait PaymentMethodExposeTrait
{
    private function exposePaymentMethod(PaymentMethod $paymentMethod): array
    {
        $response = [
            'payment_method_id' => $paymentMethod->id,
            'account_id' => $paymentMethod->account_id,
            'type' => $paymentMethod->type->name,
            'date_added' => DateTimeHelper::formatDateTime($paymentMethod->created_at->toString()),
            'is_primary' => $paymentMethod->is_primary,
            'is_autopay' => $paymentMethod->is_autopay,
            'description' => null, // we don't have a description in CRM
            'gateway' => ['id' => $paymentMethod->gateway->id, 'name' => $paymentMethod->gateway->name],
        ] + array_filter(['invalid_reason' => $paymentMethod->invalid_reason ?? null]);

        if ($paymentMethod->payment_type_id === PaymentTypeEnum::ACH->value) {
            $response += [
                'ach_account_last_four' => $paymentMethod->last_four,
                'ach_routing_number' => $paymentMethod->ach_routing_number,
                'ach_account_type' => $paymentMethod->ach_account_type ? AchAccountTypeEnum::tryFrom(value: $paymentMethod->ach_account_type) : null,
                'ach_bank_name' => $paymentMethod->ach_bank_name
            ];
        } else {
            $response += [
                'cc_type' => $paymentMethod->cc_type,
                'cc_last_four' => $paymentMethod->last_four,
                'cc_expiration_month' => $paymentMethod->cc_expiration_month,
                'cc_expiration_year' => $paymentMethod->cc_expiration_year
            ];
        }

        return $response;
    }
}
