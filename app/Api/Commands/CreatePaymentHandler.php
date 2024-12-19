<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Repositories\Interface\PaymentRepository;
use Illuminate\Support\Facades\Log;

class CreatePaymentHandler
{
    private array $paymentData = [];

    /**
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(private readonly PaymentRepository $paymentRepository)
    {
    }

    /**
     * @param CreateCheckPaymentCommand $createCheckPaymentCommand
     *
     * @return string
     */
    public function handle(CreateCheckPaymentCommand $createCheckPaymentCommand): string
    {
        $this->paymentData = $createCheckPaymentCommand->toArray();

        $this->logPaymentDetails();
        $paymentId = $this->savePaymentDataAndGetPaymentId();
        Log::info(message: __('messages.payment.create.processed', ['id' => $paymentId]));

        return $paymentId;
    }

    private function logPaymentDetails(): void
    {
        Log::info(__('messages.payment.create.process', ['id' =>  $this->paymentData['payment_type_id']]), $this->paymentData);
    }

    private function savePaymentDataAndGetPaymentId(): string
    {
        return $this->paymentRepository->create($this->paymentData)->id;
    }
}
