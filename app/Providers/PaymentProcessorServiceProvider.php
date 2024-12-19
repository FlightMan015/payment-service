<?php

declare(strict_types=1);

namespace App\Providers;

use App\Api\Commands\UpdateTokenexPaymentAccountsHandler;
use App\Api\Repositories\GatewayPaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Jobs\AccountUpdaterResultHandlerJob;
use App\Jobs\FailedPaymentRefundsReportJob;
use App\Services\Export\CsvExportService;
use App\Services\Export\ExportService;
use App\Services\FileGenerator\CsvFileGenerator;
use App\Services\FileGenerator\FileGenerator;
use App\Services\FileReader\CsvFileReader;
use App\Services\FileReader\FileReader;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Aptive\Worldpay\CredentialsRepository\DynamoDbCredentialsRepository;
use Illuminate\Support\ServiceProvider;

class PaymentProcessorServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: CredentialsRepository::class,
            concrete: static fn () => new DynamoDbCredentialsRepository(tableName: config(key: 'worldpay.dynamo_db_credentials_table'))
        );

        $this->app->singleton(
            abstract: PaymentProcessorRepository::class,
            concrete: GatewayPaymentProcessorRepository::class
        );

        $this->app->when(concrete: UpdateTokenexPaymentAccountsHandler::class)
            ->needs(abstract: ExportService::class)
            ->give(implementation: CsvExportService::class);

        $this->app->when(concrete: CsvExportService::class)
            ->needs(abstract: FileGenerator::class)
            ->give(implementation: CsvFileGenerator::class);

        $this->app->when(concrete: AccountUpdaterResultHandlerJob::class)
            ->needs(abstract: FileReader::class)
            ->give(implementation: CsvFileReader::class);

        $this->app->when(concrete: FailedPaymentRefundsReportJob::class)
            ->needs(abstract: FileGenerator::class)
            ->give(implementation: CsvFileGenerator::class);
    }
}
