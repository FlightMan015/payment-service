<?php

declare(strict_types=1);

namespace App\Models\CRM\Customer;

use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException;
use App\Models\PaymentMethod;
use Aptive\Attribution\Traits\WithAttributes;
use Database\Factories\CRM\Customer\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\CRM\Customer\Subscription
 *
 * @property string $id {"pestroutes_column_name": "subscriptionLink"}
 * @property int|null $external_ref_id {"pestroutes_column_name": "subscriptionID"}
 * @property bool $is_active {"pestroutes_column_name": "active"}
 * @property int|null $plan_id
 * @property int|null $initial_service_quote {"pestroutes_column_name": "initialQuote"}
 * @property int|null $initial_service_discount {"pestroutes_column_name": "initialDiscount"}
 * @property int|null $initial_service_total {"pestroutes_column_name": "initialServiceTotal"}
 * @property int|null $initial_status_id {"pestroutes_column_name": "initialStatus"}
 * @property int|null $recurring_charge {"pestroutes_column_name": "recurringCharge"}
 * @property int|null $contract_value {"pestroutes_column_name": "contractValue"}
 * @property int|null $annual_recurring_value {"pestroutes_column_name": "annualRecurringValue"}
 * @property string|null $billing_frequency {"pestroutes_column_name": "billingFrequency"}
 * @property string|null $service_frequency {"pestroutes_column_name": "frequency"}
 * @property int|null $days_til_follow_up_service {"pestroutes_column_name": "followupService"}
 * @property int|null $agreement_length_months {"pestroutes_column_name": "agreementLength"}
 * @property string|null $source {"pestroutes_column_name": "source"}
 * @property string|null $cancellation_notes {"pestroutes_column_name": "cxlNotes"}
 * @property int|null $annual_recurring_services {"pestroutes_column_name": "annualRecurringServices"}
 * @property int|null $renewal_frequency {"pestroutes_column_name": "renewalFrequency"}
 * @property float|null $max_monthly_charge {"pestroutes_column_name": "maxMonthlyCharge"}
 * @property string|null $initial_billing_date {"pestroutes_column_name": "initialBillingDate"}
 * @property string|null $next_service_date {"pestroutes_column_name": "nextService"}
 * @property string|null $last_service_date {"pestroutes_column_name": "lastCompleted"}
 * @property string|null $next_billing_date {"pestroutes_column_name": "nextBillingDate"}
 * @property string|null $expiration_date {"pestroutes_column_name": "expirationDate"}
 * @property string|null $custom_next_service_date {"pestroutes_column_name": "customDate"}
 * @property int|null $appt_duration_in_mins {"pestroutes_column_name": "duration"}
 * @property string|null $preferred_days_of_week {"pestroutes_column_name": "preferredDays"}
 * @property string|null $preferred_start_time_of_day {"pestroutes_column_name": "preferredStart"}
 * @property string|null $preferred_end_time_of_day {"pestroutes_column_name": "preferredEnd"}
 * @property array|null $addons {"pestroutes_column_name": "addOns"}
 * @property string|null $sold_by
 * @property string|null $sold_by_2
 * @property string|null $sold_by_3
 * @property string|null $cancelled_at
 * @property int|null $pestroutes_customer_id {"pestroutes_column_name": "customerID"}
 * @property int|null $pestroutes_created_by {"pestroutes_column_name": "addedBy"}
 * @property int|null $pestroutes_sold_by {"pestroutes_column_name": "soldBy"}
 * @property int|null $pestroutes_sold_by_2 {"pestroutes_column_name": "soldBy2"}
 * @property int|null $pestroutes_sold_by_3 {"pestroutes_column_name": "soldBy3"}
 * @property int|null $pestroutes_service_type_id {"pestroutes_column_name": "serviceID"}
 * @property int|null $pestroutes_source_id {"pestroutes_column_name": "sourceID"}
 * @property int|null $pestroutes_last_appointment_id {"pestroutes_column_name": "lastAppointment"}
 * @property int|null $pestroutes_preferred_tech_id {"pestroutes_column_name": "preferredTech"}
 * @property int|null $pestroutes_initial_appt_id {"pestroutes_column_name": "initialAppointmentID"}
 * @property string|null $pestroutes_recurring_ticket {"pestroutes_column_name": "recurringTicket"}
 * @property string|null $pestroutes_subscription_link {"pestroutes_column_name": "subscriptionLink"}
 * @property string|null $pestroutes_created_at {"pestroutes_column_name": "dateAdded"}
 * @property string|null $pestroutes_cancelled_at {"pestroutes_column_name": "dateCancelled"}
 * @property string|null $pestroutes_updated_at {"pestroutes_column_name": "dateUpdated"}
 * @property string|null $pestroutes_json
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string $account_id
 * @property-read Account|null $account
 * @property-read Collection<int, PaymentMethod> $paymentMethod
 * @property-read int|null $payment_method_count
 *
 * @method static SubscriptionFactory factory($count = null, $state = [])
 * @method static Builder|Subscription newModelQuery()
 * @method static Builder|Subscription newQuery()
 * @method static Builder|Subscription onlyTrashed()
 * @method static Builder|Subscription query()
 * @method static Builder|Subscription whereAccountId($value)
 * @method static Builder|Subscription whereAddons($value)
 * @method static Builder|Subscription whereAgreementLengthMonths($value)
 * @method static Builder|Subscription whereAnnualRecurringServices($value)
 * @method static Builder|Subscription whereAnnualRecurringValue($value)
 * @method static Builder|Subscription whereApptDurationInMins($value)
 * @method static Builder|Subscription whereBillingFrequency($value)
 * @method static Builder|Subscription whereCancellationNotes($value)
 * @method static Builder|Subscription whereCancelledAt($value)
 * @method static Builder|Subscription whereContractValue($value)
 * @method static Builder|Subscription whereCreatedAt($value)
 * @method static Builder|Subscription whereCreatedBy($value)
 * @method static Builder|Subscription whereCustomNextServiceDate($value)
 * @method static Builder|Subscription whereDaysTilFollowUpService($value)
 * @method static Builder|Subscription whereDeletedAt($value)
 * @method static Builder|Subscription whereDeletedBy($value)
 * @method static Builder|Subscription whereExpirationDate($value)
 * @method static Builder|Subscription whereExternalRefId($value)
 * @method static Builder|Subscription whereId($value)
 * @method static Builder|Subscription whereInitialBillingDate($value)
 * @method static Builder|Subscription whereInitialServiceDiscount($value)
 * @method static Builder|Subscription whereInitialServiceQuote($value)
 * @method static Builder|Subscription whereInitialServiceTotal($value)
 * @method static Builder|Subscription whereInitialStatusId($value)
 * @method static Builder|Subscription whereIsActive($value)
 * @method static Builder|Subscription whereLastServiceDate($value)
 * @method static Builder|Subscription whereMaxMonthlyCharge($value)
 * @method static Builder|Subscription whereNextBillingDate($value)
 * @method static Builder|Subscription whereNextServiceDate($value)
 * @method static Builder|Subscription wherePestroutesCancelledAt($value)
 * @method static Builder|Subscription wherePestroutesCreatedAt($value)
 * @method static Builder|Subscription wherePestroutesCreatedBy($value)
 * @method static Builder|Subscription wherePestroutesCustomerId($value)
 * @method static Builder|Subscription wherePestroutesInitialApptId($value)
 * @method static Builder|Subscription wherePestroutesJson($value)
 * @method static Builder|Subscription wherePestroutesLastAppointmentId($value)
 * @method static Builder|Subscription wherePestroutesPreferredTechId($value)
 * @method static Builder|Subscription wherePestroutesRecurringTicket($value)
 * @method static Builder|Subscription wherePestroutesServiceTypeId($value)
 * @method static Builder|Subscription wherePestroutesSoldBy($value)
 * @method static Builder|Subscription wherePestroutesSoldBy2($value)
 * @method static Builder|Subscription wherePestroutesSoldBy3($value)
 * @method static Builder|Subscription wherePestroutesSourceId($value)
 * @method static Builder|Subscription wherePestroutesSubscriptionLink($value)
 * @method static Builder|Subscription wherePestroutesUpdatedAt($value)
 * @method static Builder|Subscription wherePlanId($value)
 * @method static Builder|Subscription wherePreferredDaysOfWeek($value)
 * @method static Builder|Subscription wherePreferredEndTimeOfDay($value)
 * @method static Builder|Subscription wherePreferredStartTimeOfDay($value)
 * @method static Builder|Subscription whereRecurringCharge($value)
 * @method static Builder|Subscription whereRenewalFrequency($value)
 * @method static Builder|Subscription whereServiceFrequency($value)
 * @method static Builder|Subscription whereSoldBy($value)
 * @method static Builder|Subscription whereSoldBy2($value)
 * @method static Builder|Subscription whereSoldBy3($value)
 * @method static Builder|Subscription whereSource($value)
 * @method static Builder|Subscription whereUpdatedAt($value)
 * @method static Builder|Subscription whereUpdatedBy($value)
 * @method static Builder|Subscription withTrashed()
 * @method static Builder|Subscription withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Subscription extends Model
{
    use SoftDeletes;
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;
    use HasUuids;
    use WithAttributes;

    protected $table = 'customer.subscriptions';

    protected $guarded = [
        'id',
        'initial_service_quote',
        'initial_service_discount',
        'annual_recurring_value',
        'contract_value'
    ];

    protected $casts = [
        'addons' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return BelongsTo<Account, Subscription>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsToMany<PaymentMethod>
     */
    public function paymentMethod(): BelongsToMany
    {
        return $this->belongsToMany(
            PaymentMethod::class,
            'billing.subscription_autopay_payment_methods',
            'subscription_id',
            'payment_method_id'
        );
    }

    /**
     * @param PaymentMethod|null $autopayPaymentMethod
     *
     * @return void
     */
    public function setAutoPayPaymentMethod(PaymentMethod|null $autopayPaymentMethod): void
    {
        if (!is_null($autopayPaymentMethod) && $this->account_id !== $autopayPaymentMethod->account_id) {
            throw new PaymentMethodDoesNotBelongToAccountAssociatedWithSubscriptionException(
                paymentMethodId: $autopayPaymentMethod->id,
                accountId: $this->account_id,
                subscriptionId: $this->id
            );
        }

        if (is_null($autopayPaymentMethod)) {
            $this->paymentMethod()->detach();

            return;
        }

        $this->paymentMethod()->sync($autopayPaymentMethod);
    }
}
