<?php

declare(strict_types=1);

namespace Tests\Stubs\CRM;

class SubscriptionResponses
{
    /**
     * @return object
     */
    public static function getSingle(): object
    {
        return (object) [
            'id' => '7cbf6aa0-422a-41dd-9aa7-85205c1406b8',
            'plan_id' => 1,
            'is_active' => true,
            'initial_service_quote' => 21900,
            'initial_service_discount' => 12000,
            'initial_service_total' => 99,
            'initial_status_id' => 1,
            'recurring_charge' => 146,
            'contract_value' => 53700,
            'annual_recurring_value' => 58400,
            'billing_frequency' => '-1',
            'service_frequency' => '90',
            'days_til_follow_up_service' => 90,
            'agreement_length_months' => 12,
            'source' => null,
            'cancellation_notes' => null,
            'annual_recurring_services' => 4,
            'renewal_frequency' => 360,
            'max_monthly_charge' => '0',
            'initial_billing_date' => '2020-04-21',
            'next_service_date' => '2023-02-05',
            'last_service_date' => '2022-11-05',
            'next_billing_date' => '2022-12-18',
            'expiration_date' => null,
            'custom_next_service_date' => null,
            'appt_duration_in_mins' => 30,
            'preferred_days_of_week' => '',
            'preferred_start_time_of_day' => null,
            'preferred_end_time_of_day' => null,
            'addons' => [],
            'sold_by' => null,
            'sold_by_2' => null,
            'sold_by_3' => null,
            'account_id' => '612c9dcc-4932-4baf-af63-98baf32ad691',
            'external_ref_id' => 639311,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => '2024-01-02T17:35:59.991021Z',
            'updated_at' => '2024-06-05T17:41:43.994459Z'
        ];
    }
}
