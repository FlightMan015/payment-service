<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Gateway;
use Illuminate\Contracts\Validation\Rule;

class GatewayExistsAndActive implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        return Gateway::whereId($value)->whereIsEnabled(true)->whereIsHidden(false)->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return __('messages.gateway.not_found');
    }
}
