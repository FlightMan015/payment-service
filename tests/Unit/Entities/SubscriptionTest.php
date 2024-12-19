<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\Subscription;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\CRM\SubscriptionResponses;
use Tests\Unit\UnitTestCase;

class SubscriptionTest extends UnitTestCase
{
    #[Test]
    public function it_creates_entity_from_object_as_expected(): void
    {
        $object = SubscriptionResponses::getSingle();

        $entity = Subscription::fromObject($object);

        $this->assertEquals($object->id, $entity->id);
        $this->assertEquals($object->account_id, $entity->accountId);
        $this->assertEquals($object->external_ref_id, $entity->externalRefId);
        $this->assertEquals($object->is_active, $entity->isActive);
        $this->assertEquals($object->plan_id, $entity->planId);
        $this->assertEquals($object->initial_service_quote, $entity->initialServiceQuote);
        $this->assertEquals($object->initial_service_discount, $entity->initialServiceDiscount);
        $this->assertEquals($object->initial_service_total, $entity->initialServiceTotal);
        $this->assertEquals($object->initial_status_id, $entity->initialStatus->value);
        $this->assertEquals($object->recurring_charge, $entity->recurringCharge);
        $this->assertEquals($object->contract_value, $entity->contractValue);
        $this->assertEquals($object->annual_recurring_value, $entity->annualRecurringValue);
        $this->assertEquals($object->billing_frequency, $entity->billingFrequency);
        $this->assertEquals($object->service_frequency, $entity->serviceFrequency);
        $this->assertEquals($object->days_til_follow_up_service, $entity->daysTilFollowUpService);
        $this->assertEquals($object->agreement_length_months, $entity->agreementLengthMonths);
        $this->assertEquals($object->source, $entity->source);
        $this->assertEquals($object->cancellation_notes, $entity->cancellationNotes);
        $this->assertEquals($object->annual_recurring_services, $entity->annualRecurringServices);
        $this->assertEquals($object->renewal_frequency, $entity->renewalFrequency);
        $this->assertEquals($object->max_monthly_charge, $entity->maxMonthlyCharge);
        $this->assertEquals($object->initial_billing_date, $entity->initialBillingDate->format('Y-m-d'));
        $this->assertEquals($object->next_service_date, $entity->nextServiceDate->format('Y-m-d'));
        $this->assertEquals($object->last_service_date, $entity->lastServiceDate->format('Y-m-d'));
        $this->assertEquals($object->next_billing_date, $entity->nextBillingDate->format('Y-m-d'));
        $this->assertEquals($object->expiration_date, $entity->expirationDate);
        $this->assertEquals($object->custom_next_service_date, $entity->customNextServiceDate);
        $this->assertEquals($object->appt_duration_in_mins, $entity->appointmentDurationInMinutes);
        $this->assertEquals($object->preferred_days_of_week, $entity->preferredDaysOfWeek);
    }
}
