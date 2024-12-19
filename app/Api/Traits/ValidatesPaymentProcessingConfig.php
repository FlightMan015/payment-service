<?php

declare(strict_types=1);

namespace App\Api\Traits;

trait ValidatesPaymentProcessingConfig
{
    private function validateConfig(): void
    {
        if (!isset($this->config['isPestRoutesBalanceCheckEnabled'])) {
            throw new \InvalidArgumentException(message: __('messages.config.missing_value', ['value' => 'isPestRoutesBalanceCheckEnabled']));
        }

        if (!isset($this->config['isPestRoutesAutoPayCheckEnabled'])) {
            throw new \InvalidArgumentException(message: __('messages.config.missing_value', ['value' => 'isPestRoutesAutoPayCheckEnabled']));
        }

        if (!isset($this->config['isPestRoutesPaymentHoldDateCheckEnabled'])) {
            throw new \InvalidArgumentException(message: __('messages.config.missing_value', ['value' => 'isPestRoutesPaymentHoldDateCheckEnabled']));
        }

        if (!isset($this->config['isPestRoutesInvoiceCheckEnabled'])) {
            throw new \InvalidArgumentException(message: __('messages.config.missing_value', ['value' => 'isPestRoutesInvoiceCheckEnabled']));
        }
    }
}
