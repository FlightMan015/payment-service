<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WorldpayPaymentMethodUpdateExpirationDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use RetrieveGatewayForPaymentMethodTrait;

    /**
     * @param PaymentMethod $paymentMethod
     */
    public function __construct(private readonly PaymentMethod $paymentMethod)
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
    }

    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     *
     * @throws BindingResolutionException
     * @throws UnsupportedValueException
     */
    public function handle(PaymentMethodRepository $paymentMethodRepository): void
    {
        Log::info(
            message: __('messages.worldpay.populate_expiration_data.start_updating'),
            context: ['payment_method_id' => $this->paymentMethod->id]
        );

        $this->getGatewayInstanceBasedOnPaymentMethod();
        $paymentAccount = $this->gateway->getPaymentAccount($this->paymentMethod->cc_token);

        if (is_null($paymentAccount)) {
            Log::warning(
                message: __('messages.worldpay.populate_expiration_data.account_not_found'),
                context: ['payment_method_id' => $this->paymentMethod->id]
            );
            return;
        }

        try {
            $this->validatePaymentAccount($paymentAccount);
        } catch (\InvalidArgumentException $e) {
            Log::warning(message: __(
                key: 'messages.worldpay.populate_expiration_data.account_validation_failed',
                replace: ['message' => $e->getMessage()]
            ));
            return;
        }

        $paymentMethodRepository->update(
            paymentMethod: $this->paymentMethod,
            attributes: [
                'cc_expiration_month' => (int)$paymentAccount->ExpirationMonth,
                'cc_expiration_year' => (int)Carbon::createFromFormat(format: 'y', time: $paymentAccount->ExpirationYear)->format(format: 'Y'),
            ]
        );

        Log::info(
            message: __('messages.worldpay.populate_expiration_data.updated'),
            context: [
                'payment_method_id' => $this->paymentMethod->id,
                'cc_expiration_month' => $this->paymentMethod->cc_expiration_month,
                'cc_expiration_year' => $this->paymentMethod->cc_expiration_year
            ]
        );
    }

    private function validatePaymentAccount(object $paymentAccount): void
    {
        if (!property_exists($paymentAccount, 'ExpirationMonth') || !property_exists($paymentAccount, 'ExpirationYear')) {
            throw new \InvalidArgumentException(message: __('messages.worldpay.populate_expiration_data.expiration_data_missing'));
        }

        if (empty($paymentAccount->ExpirationMonth) || empty($paymentAccount->ExpirationYear)) {
            throw new \InvalidArgumentException(message: __('messages.worldpay.populate_expiration_data.expiration_data_empty'));
        }
    }
}
