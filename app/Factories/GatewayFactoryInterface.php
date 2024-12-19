<?php

declare(strict_types=1);

namespace App\Factories;

use App\Api\DTO\GatewayInitializationDTO;

interface GatewayFactoryInterface
{
    /**
     * @param GatewayInitializationDTO $gatewayInitializationDTO
     *
     * @return mixed
     */
    public static function make(GatewayInitializationDTO $gatewayInitializationDTO): mixed;
}
