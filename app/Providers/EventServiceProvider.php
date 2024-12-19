<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\PaymentAttemptedEvent;
use App\Events\PaymentReturnedEvent;
use App\Events\PaymentScheduledEvent;
use App\Events\PaymentSettledEvent;
use App\Events\PaymentSkippedEvent;
use App\Events\PaymentSuspendedEvent;
use App\Events\PaymentTerminatedEvent;
use App\Events\RefundPaymentFailedEvent;
use App\Events\ScheduledPaymentCancelledEvent;
use App\Events\ScheduledPaymentSubmittedEvent;
use App\Events\SuspendedPaymentProcessedEvent;
use App\Events\SuspendedPaymentUpdatedEvent;
use App\Listeners\CollectPaymentMetricsListener;
use App\Listeners\CollectPaymentReturnedMetricsListener;
use App\Listeners\CollectPaymentScheduledMetricsListener;
use App\Listeners\CollectPaymentSettledMetricsListener;
use App\Listeners\CollectPaymentSkippedMetricsListener;
use App\Listeners\CollectPaymentSuspendedMetricsListener;
use App\Listeners\CollectPaymentTerminatedMetricsListener;
use App\Listeners\CollectRefundPaymentFailedMetricsListener;
use App\Listeners\CollectScheduledPaymentCancelledMetricsListener;
use App\Listeners\CollectScheduledPaymentSubmittedMetricsListener;
use App\Listeners\CollectSuspendedPaymentProcessedMetricsListener;
use App\Listeners\CollectSuspendedPaymentUpdatedMetricsListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        PaymentAttemptedEvent::class => [
            CollectPaymentMetricsListener::class,
        ],
        PaymentSkippedEvent::class => [
            CollectPaymentSkippedMetricsListener::class,
        ],
        PaymentSuspendedEvent::class => [
            CollectPaymentSuspendedMetricsListener::class,
        ],
        SuspendedPaymentUpdatedEvent::class => [
            CollectSuspendedPaymentUpdatedMetricsListener::class,
        ],
        PaymentScheduledEvent::class => [
            CollectPaymentScheduledMetricsListener::class,
        ],
        ScheduledPaymentCancelledEvent::class => [
            CollectScheduledPaymentCancelledMetricsListener::class,
        ],
        ScheduledPaymentSubmittedEvent::class => [
            CollectScheduledPaymentSubmittedMetricsListener::class,
        ],
        RefundPaymentFailedEvent::class => [
            CollectRefundPaymentFailedMetricsListener::class,
        ],
        SuspendedPaymentProcessedEvent::class => [
            CollectSuspendedPaymentProcessedMetricsListener::class,
        ],
        PaymentTerminatedEvent::class => [
            CollectPaymentTerminatedMetricsListener::class
        ],
        PaymentReturnedEvent::class => [
            CollectPaymentReturnedMetricsListener::class,
        ],
        PaymentSettledEvent::class => [
            CollectPaymentSettledMetricsListener::class,
        ],
    ];
}
