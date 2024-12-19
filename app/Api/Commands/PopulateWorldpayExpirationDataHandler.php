<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Jobs\WorldpayPaymentMethodUpdateExpirationDataJob;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PopulateWorldpayExpirationDataHandler
{
    private const int PAGINATOR_PER_PAGE_SIZE = 100;

    /** @var Collection<int, PaymentMethod> */
    private Collection $paymentMethods;

    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     */
    public function __construct(private readonly PaymentMethodRepository $paymentMethodRepository)
    {
        $this->paymentMethods = collect();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        Log::info(message: __('messages.worldpay.populate_expiration_data.start'));

        $this->retrievePaymentMethods();
        $this->dispatchUpdateExpirationDataJobs();

        Log::info(message: __('messages.worldpay.populate_expiration_data.end'));
    }

    private function retrievePaymentMethods(): void
    {
        do {
            $paginator = $this->paymentMethodRepository->filter(
                filter: [
                    'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                    'type' => PaymentTypeEnum::CC->name,
                    'has_cc_token' => true,
                    'has_cc_expiration_data' => false,
                    'per_page' => self::PAGINATOR_PER_PAGE_SIZE,
                    'page' => $page ?? 1,
                ],
                columns: [
                    'id',
                    'cc_token',
                    'account_id'
                ],
                withRelations: ['account' => ['area:id,external_ref_id']]
            );
            $page = $paginator->currentPage() + 1;
            $this->paymentMethods = $this->paymentMethods->merge(items: $paginator->collect());
        } while ($paginator->hasMorePages());

        Log::info(
            message: __('messages.worldpay.populate_expiration_data.payment_methods_loaded'),
            context: ['total_count' => $this->paymentMethods->count()]
        );
    }

    private function dispatchUpdateExpirationDataJobs(): void
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            WorldpayPaymentMethodUpdateExpirationDataJob::dispatch($paymentMethod);
        }
    }
}
