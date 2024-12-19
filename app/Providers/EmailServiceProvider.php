<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Communication\Email\EmailService;
use App\Services\Communication\Email\MarketingEmailService;
use Illuminate\Support\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->app->bind(abstract: EmailService::class, concrete: MarketingEmailService::class);
    }
}
