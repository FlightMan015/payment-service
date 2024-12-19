<?php

declare(strict_types=1);

namespace App\Factories;

use App\Api\DTO\GatewayInitializationDTO;
use App\Api\Exceptions\UnsupportedValueException;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Gateways\Worldpay;
use App\PaymentProcessor\Gateways\WorldpayTokenexTransparent;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Illuminate\Contracts\Container\BindingResolutionException;

class PaymentGatewayFactory implements GatewayFactoryInterface
{
    /**
     * @param GatewayInitializationDTO $gatewayInitializationDTO
     *
     * @throws BindingResolutionException
     * @throws UnsupportedValueException
     *
     * @return GatewayInterface
     */
    public static function make(GatewayInitializationDTO $gatewayInitializationDTO): GatewayInterface
    {
        $worldPayCredentialsRepository = app()->make(CredentialsRepository::class);

        return match (PaymentGatewayEnum::tryFrom($gatewayInitializationDTO->gatewayId)) {
            PaymentGatewayEnum::WORLDPAY => Worldpay::make(
                credentials: $worldPayCredentialsRepository->get($gatewayInitializationDTO->officeId)
            ),
            PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT => WorldpayTokenexTransparent::make(
                gatewayInitializationDTO: $gatewayInitializationDTO,
                credentials: $worldPayCredentialsRepository->get($gatewayInitializationDTO->officeId)
            ),
            default => throw new UnsupportedValueException(message: __('messages.gateway.not_implemented')),
        };
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @throws BindingResolutionException
     * @throws UnsupportedValueException
     *
     * @return GatewayInterface
     */
    public static function makeForPaymentMethod(PaymentMethod $paymentMethod): GatewayInterface
    {
        return self::make(
            gatewayInitializationDTO: GatewayInitializationDTO::fromPaymentMethod($paymentMethod)
        );
    }
}
