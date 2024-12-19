<?php

declare(strict_types=1);

namespace App\Entities;

use App\Entities\Enums\SubscriptionInitialStatusEnum;

readonly class Subscription
{
    /**
     * @param string $id
     * @param string $accountId
     * @param int $externalRefId
     * @param bool $isActive
     * @param int $planId
     * @param int $initialServiceQuote
     * @param int $initialServiceDiscount
     * @param int $initialServiceTotal
     * @param SubscriptionInitialStatusEnum|null $initialStatus
     * @param int|null $recurringCharge
     * @param int $contractValue
     * @param int $annualRecurringValue
     * @param string $billingFrequency
     * @param string $serviceFrequency
     * @param int $daysTilFollowUpService
     * @param int $agreementLengthMonths
     * @param string|null $source
     * @param string|null $cancellationNotes
     * @param int $annualRecurringServices
     * @param int $renewalFrequency
     * @param float $maxMonthlyCharge
     * @param \DateTimeInterface|null $initialBillingDate
     * @param \DateTimeInterface|null $nextServiceDate
     * @param \DateTimeInterface|null $lastServiceDate
     * @param \DateTimeInterface|null $nextBillingDate
     * @param \DateTimeInterface|null $expirationDate
     * @param \DateTimeInterface|null $customNextServiceDate
     * @param int $appointmentDurationInMinutes
     * @param string|null $preferredDaysOfWeek
     * @param \DateTimeInterface|null $preferredStartTimeOfDay
     * @param \DateTimeInterface|null $preferredEndTimeOfDay
     * @param array $addons
     * @param int|null $soldBy
     * @param \DateTimeInterface $createdAt
     */
    public function __construct(
        public string $id,
        public string $accountId,
        public int $externalRefId,
        public bool $isActive,
        public int $planId,
        public int $initialServiceQuote,
        public int $initialServiceDiscount,
        public int $initialServiceTotal,
        public SubscriptionInitialStatusEnum|null $initialStatus,
        public int|null $recurringCharge,
        public int $contractValue,
        public int $annualRecurringValue,
        public string $billingFrequency,
        public string $serviceFrequency,
        public int $daysTilFollowUpService,
        public int $agreementLengthMonths,
        public string|null $source,
        public string|null $cancellationNotes,
        public int $annualRecurringServices,
        public int $renewalFrequency,
        public float $maxMonthlyCharge,
        public \DateTimeInterface|null $initialBillingDate,
        public \DateTimeInterface|null $nextServiceDate,
        public \DateTimeInterface|null $lastServiceDate,
        public \DateTimeInterface|null $nextBillingDate,
        public \DateTimeInterface|null $expirationDate,
        public \DateTimeInterface|null $customNextServiceDate,
        public int $appointmentDurationInMinutes,
        public string|null $preferredDaysOfWeek,
        public \DateTimeInterface|null $preferredStartTimeOfDay,
        public \DateTimeInterface|null $preferredEndTimeOfDay,
        public array $addons,
        public int|null $soldBy,
        public \DateTimeInterface $createdAt
    ) {
    }

    /**
     * @param object $subscription
     *
     * @throws \Exception
     *
     * @return Subscription
     */
    public static function fromObject(object $subscription): self
    {
        return new self(
            id: $subscription->id,
            accountId: $subscription->account_id,
            externalRefId: $subscription->external_ref_id,
            isActive: $subscription->is_active,
            planId: $subscription->plan_id,
            initialServiceQuote: $subscription->initial_service_quote,
            initialServiceDiscount: $subscription->initial_service_discount,
            initialServiceTotal: $subscription->initial_service_total,
            initialStatus: $subscription->initial_status_id ? SubscriptionInitialStatusEnum::tryFrom($subscription->initial_status_id) : null,
            recurringCharge: $subscription->recurring_charge,
            contractValue: $subscription->contract_value,
            annualRecurringValue: $subscription->annual_recurring_value,
            billingFrequency: $subscription->billing_frequency,
            serviceFrequency: $subscription->service_frequency,
            daysTilFollowUpService: $subscription->days_til_follow_up_service,
            agreementLengthMonths: $subscription->agreement_length_months,
            source: $subscription->source,
            cancellationNotes: $subscription->cancellation_notes,
            annualRecurringServices: $subscription->annual_recurring_services,
            renewalFrequency: $subscription->renewal_frequency,
            maxMonthlyCharge: (float)$subscription->max_monthly_charge,
            initialBillingDate: $subscription->initial_billing_date ? new \DateTimeImmutable($subscription->initial_billing_date) : null,
            nextServiceDate: $subscription->next_service_date ? new \DateTimeImmutable($subscription->next_service_date) : null,
            lastServiceDate: $subscription->last_service_date ? new \DateTimeImmutable($subscription->last_service_date) : null,
            nextBillingDate: $subscription->next_billing_date ? new \DateTimeImmutable($subscription->next_billing_date) : null,
            expirationDate: $subscription->expiration_date ? new \DateTimeImmutable($subscription->expiration_date) : null,
            customNextServiceDate: $subscription->custom_next_service_date ? new \DateTimeImmutable($subscription->custom_next_service_date) : null,
            appointmentDurationInMinutes: $subscription->appt_duration_in_mins,
            preferredDaysOfWeek: $subscription->preferred_days_of_week ?: null,
            preferredStartTimeOfDay: $subscription->preferred_start_time_of_day ? new \DateTimeImmutable($subscription->preferred_start_time_of_day) : null,
            preferredEndTimeOfDay: $subscription->preferred_end_time_of_day ? new \DateTimeImmutable($subscription->preferred_end_time_of_day) : null,
            addons: $subscription->addons,
            soldBy: $subscription->sold_by,
            createdAt: new \DateTimeImmutable($subscription->created_at),
        );
    }
}
