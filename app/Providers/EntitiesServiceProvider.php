<?php

declare(strict_types=1);

namespace App\Providers;

use App\Api\Repositories\DatabaseScheduledPaymentRepository;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Infrastructure\CRM\CrmSubscriptionService;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use Illuminate\Support\ServiceProvider;

class EntitiesServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        // services
        $this->app->bind(abstract: SubscriptionServiceInterface::class, concrete: CrmSubscriptionService::class);

        // repositories
        $this->app->bind(abstract: ScheduledPaymentRepository::class, concrete: DatabaseScheduledPaymentRepository::class);
    }
}
