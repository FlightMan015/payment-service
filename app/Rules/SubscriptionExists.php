<?php

declare(strict_types=1);

namespace App\Rules;

use App\Api\Repositories\CRM\SubscriptionRepository;
use Illuminate\Contracts\Validation\Rule;

class SubscriptionExists implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @param SubscriptionRepository $subscriptionRepository
     *
     * @return void
     */
    public function __construct(private readonly SubscriptionRepository $subscriptionRepository)
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        return $this->subscriptionRepository->exists(id: $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return __('messages.subscription.not_found_in_db');
    }
}
