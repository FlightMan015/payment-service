<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums\Database;

enum PaymentGatewayEnum: int
{
    case WORLDPAY = 1;
    case WORLDPAY_TOKENEX_TRANSPARENT = 2;
    case CHECK = 3;
    case CREDIT = 4;

    /**
     * Returns list of gateway that belongs to Tokenex
     *
     * @return PaymentGatewayEnum[]
     */
    public static function tokenexGateways(): array
    {
        return array_filter(
            array: self::cases(),
            callback: static fn (PaymentGatewayEnum $gateway) => $gateway->isTokenexGateway()
        );
    }

    /**
     * Identifies if gateway belongs to Tokenex
     *
     * @return bool
     */
    public function isTokenexGateway(): bool
    {
        return match($this) {
            self::WORLDPAY_TOKENEX_TRANSPARENT => true,
            default => false,
        };
    }

    /**
     * Returns list of gateway that are adequate real gateways
     *
     * @return PaymentGatewayEnum[]
     */
    public static function realGateways(): array
    {
        return array_filter(
            array: self::cases(),
            callback: static fn (PaymentGatewayEnum $gateway) => $gateway->isRealGateway()
        );
    }

    /**
     * Identifies if gateway belongs to Tokenex
     *
     * @return bool
     */
    public function isRealGateway(): bool
    {
        return match($this) {
            self::WORLDPAY_TOKENEX_TRANSPARENT => true,
            self::WORLDPAY => true,
            default => false,
        };
    }
}
