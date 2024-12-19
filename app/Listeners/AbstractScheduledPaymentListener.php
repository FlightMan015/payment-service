<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Entities\Subscription;
use App\Events\AbstractScheduledPaymentEvent;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\Models\ScheduledPayment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InfluxDB2\Client as InfluxClient;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;

abstract class AbstractScheduledPaymentListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected readonly WriteApi $writeApi;
    protected ScheduledPayment $payment;

    /**
     * Create the event listener.
     *
     * @param InfluxClient $influxClient
     * @param SubscriptionServiceInterface $subscriptionService
     */
    public function __construct(
        private readonly InfluxClient $influxClient,
        private readonly SubscriptionServiceInterface $subscriptionService
    ) {
        $this->writeApi = $this->influxClient->createWriteApi();
    }

    /**
     * @return string the name of the listener's queue
     */
    public function viaQueue(): string
    {
        return config(key: 'queue.connections.sqs.queues.collect_metrics');
    }

    /**
     * Handle the event.
     *
     * @param AbstractScheduledPaymentEvent $event
     *
     * @throws \Exception
     */
    abstract public function handle(AbstractScheduledPaymentEvent $event): void;

    /**
     * Handle a job failure.
     *
     * @param AbstractScheduledPaymentEvent $event
     * @param \Throwable $exception
     */
    abstract public function failed(AbstractScheduledPaymentEvent $event, \Throwable $exception): void;

    /**
     * @param string $name
     * @param AbstractScheduledPaymentEvent $event
     *
     * @throws \Exception
     *
     * @return Point
     */
    protected function buildDatapoint(string $name, AbstractScheduledPaymentEvent $event): Point
    {
        $this->payment = $event->payment;
        $subscription = $this->retrieveSubscription();

        $point = new Point($name);
        $point
            ->addField('amount', $this->payment->getDecimalAmount())
            ->addField('scheduled_payment_id', $this->payment->id)
            ->addField('payment_method_id', $this->payment->paymentMethod->id)
            ->addField('payment_method_type', $this->payment->paymentMethod->type->name)
            ->addField('customer_external_reference_id', $this->payment->account->external_ref_id)
            ->addField('customer_account_id', $this->payment->account_id)
            ->addField('office_id', $this->payment->account->area->external_ref_id)
            ->addField('area_id', $this->payment->account->area->id)
            ->addField('subscription_id', $subscription?->id)
            ->addField('plan_id', $subscription?->planId)
            ->addField('trigger_id', $this->payment->trigger_id)
            ->addTag('office', $this->payment->account->area->name)
            ->addTag('trigger', $this->payment->trigger->name)
            ->time($event->timestamp);

        return $point;
    }

    /**
     * Retrieve the subscription if it's relevant for the payment with this trigger
     *
     * @return Subscription|null
     */
    private function retrieveSubscription(): Subscription|null
    {
        if (!isset($this->payment->metadata->subscription_id)) {
            return null;
        }

        try {
            return $this->subscriptionService->getSubscription(id: $this->payment->metadata->subscription_id);
        } catch (\Exception $e) {
            Log::warning(message: __('messages.scheduled_payment.subscription_not_found'), context: [
                'payment_id' => $this->payment->id,
                'subscription_id' => $this->payment->metadata->subscription_id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }
}
