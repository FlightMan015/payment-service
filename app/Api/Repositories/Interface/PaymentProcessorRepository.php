<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Api\DTO\PaymentProcessorResultDto;
use App\Models\Payment;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\PaymentProcessor;

interface PaymentProcessorRepository
{
    /**
     * Authorize payment
     *
     * @param PaymentProcessor $paymentProcessor
     * @param Payment $payment
     * @param GatewayInterface $gateway
     *
     * @return PaymentProcessorResultDto
     */
    public function authorize(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto;

    /**
     * Capture payment
     *
     * @param PaymentProcessor $paymentProcessor
     * @param Payment $payment
     * @param GatewayInterface $gateway
     *
     * @return PaymentProcessorResultDto
     */
    public function capture(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto;

    /**
     * Cancel a payment
     *
     * @param PaymentProcessor $paymentProcessor
     * @param Payment $payment
     * @param GatewayInterface $gateway
     *
     * @return PaymentProcessorResultDto
     */
    public function cancel(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto;

    /**
     * Authorize and capture a payment
     *
     * @param PaymentProcessor $paymentProcessor
     * @param Payment $payment
     * @param GatewayInterface $gateway
     *
     * @return PaymentProcessorResultDto
     */
    public function authorizeAndCapture(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto;

    /**
     * Check a payment status
     *
     * @param PaymentProcessor $paymentProcessor
     * @param Payment $payment
     *
     * @return PaymentProcessorResultDto
     */
    public function status(
        PaymentProcessor $paymentProcessor,
        Payment $payment
    ): PaymentProcessorResultDto;
}
