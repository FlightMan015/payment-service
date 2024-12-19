<?php

declare(strict_types=1);

namespace App\Api\Providers;

use App\Api\Controllers\ProcessPaymentsController;
use App\Api\Repositories\DatabaseAccountUpdaterAttemptRepository;
use App\Api\Repositories\DatabaseFailedJobRepository;
use App\Api\Repositories\DatabaseFailedRefundPaymentRepository;
use App\Api\Repositories\DatabaseInvoiceRepository;
use App\Api\Repositories\DatabasePaymentInvoiceRepository;
use App\Api\Repositories\DatabasePaymentMethodRepository;
use App\Api\Repositories\DatabasePaymentRepository;
use App\Api\Repositories\DatabasePaymentTransactionRepository;
use App\Api\Repositories\DatabaseScheduledPaymentRepository;
use App\Api\Repositories\Interface\AccountUpdaterAttemptRepository;
use App\Api\Repositories\Interface\FailedJobRepository;
use App\Api\Repositories\Interface\FailedRefundPaymentRepository;
use App\Api\Repositories\Interface\InvoiceRepository;
use App\Api\Repositories\Interface\PaymentInvoiceRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use ConfigCat\ClientInterface as ConfigCatClientInterface;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /** @inheritDoc */
    public function register(): void
    {
        $this->app->bind(abstract: PaymentRepository::class, concrete: DatabasePaymentRepository::class);
        $this->app->bind(abstract: PaymentMethodRepository::class, concrete: DatabasePaymentMethodRepository::class);
        $this->app->bind(abstract: PaymentTransactionRepository::class, concrete: DatabasePaymentTransactionRepository::class);
        $this->app->bind(abstract: FailedJobRepository::class, concrete: DatabaseFailedJobRepository::class);
        $this->app->bind(abstract: PaymentInvoiceRepository::class, concrete: DatabasePaymentInvoiceRepository::class);
        $this->app->bind(abstract: AccountUpdaterAttemptRepository::class, concrete: DatabaseAccountUpdaterAttemptRepository::class);
        $this->app->bind(abstract: InvoiceRepository::class, concrete: DatabaseInvoiceRepository::class);
        $this->app->bind(abstract: ScheduledPaymentRepository::class, concrete: DatabaseScheduledPaymentRepository::class);
        $this->app->bind(abstract: FailedRefundPaymentRepository::class, concrete: DatabaseFailedRefundPaymentRepository::class);

        $this->bindBatchPaymentProcessingDependencies();
    }

    private function bindBatchPaymentProcessingDependencies(): void
    {
        $this->app->when(concrete: ProcessPaymentsController::class)
            ->needs('$config')
            ->give(function () {
                /** @var ConfigCatClientInterface $configCatClient */
                $configCatClient = $this->app->make(abstract: ConfigCatClientInterface::class);

                return [
                    'isPestRoutesBalanceCheckEnabled' => $configCatClient->getValue(
                        key: 'isPestRoutesBalanceCheckEnabled',
                        defaultValue: true
                    ),
                    'isPestRoutesAutoPayCheckEnabled' => $configCatClient->getValue(
                        key: 'isPestRoutesAutoPayCheckEnabled',
                        defaultValue: true
                    ),
                    'isPestRoutesPaymentHoldDateCheckEnabled' => $configCatClient->getValue(
                        key: 'isPestRoutesPaymentHoldDateCheckEnabled',
                        defaultValue: true
                    ),
                    'isPestRoutesInvoiceCheckEnabled' => $configCatClient->getValue(
                        key: 'isPestRoutesInvoiceCheckEnabled',
                        defaultValue: true
                    ),
                ];
            });
    }
}
