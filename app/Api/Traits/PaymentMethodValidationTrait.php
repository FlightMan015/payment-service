<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Api\Exceptions\InvalidPaymentMethodException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;

trait PaymentMethodValidationTrait
{
    use RetrieveGatewayForPaymentMethodTrait;

    private Payment $payment;
    private PaymentMethod|null $paymentMethod = null;

    /**
     * @param PaymentProcessor $paymentProcessor
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly PaymentRepository $paymentRepository
    ) {
    }

    /**
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    private function validatePaymentMethodInGateway(): void
    {
        $this->getGatewayInstanceBasedOnPaymentMethod();
        $this->paymentProcessor->setGateway(gateway: $this->gateway);

        DB::transaction(callback: function () {
            $this->createPaymentRecord();
            $this->populatePaymentProcessor();

            $this->paymentMethodRepository->save(paymentMethod: $this->paymentMethod);

            $isSuccessful = $this->paymentProcessor->authorize();

            $this->paymentRepository->update(payment: $this->payment, attributes: [
                'payment_status_id' =>  $isSuccessful ? PaymentStatusEnum::AUTHORIZED : PaymentStatusEnum::DECLINED,
            ]);

            PaymentAttemptedEvent::dispatch($this->payment, PaymentProcessingInitiator::API_REQUEST, OperationEnum::AUTHORIZE);

            throw_unless(
                condition: $isSuccessful,
                exception: new InvalidPaymentMethodException(paymentMethodId: $this->paymentMethod->id)
            );
        });
    }

    private function createPaymentRecord(): void
    {
        $this->payment = $this->paymentRepository->create(attributes: [
            'account_id' => $this->paymentMethod->account_id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::AUTHORIZING,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => 0,
            'applied_amount' => 0,
            'processed_at' => now(),
        ]);
    }

    abstract protected function populatePaymentProcessor(): void;
}
