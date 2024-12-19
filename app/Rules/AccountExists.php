<?php

declare(strict_types=1);

namespace App\Rules;

use App\Api\Repositories\CRM\AccountRepository;
use Illuminate\Contracts\Validation\Rule;

class AccountExists implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @param AccountRepository $accountRepository
     *
     * @return void
     */
    public function __construct(private readonly AccountRepository $accountRepository)
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
        return $this->accountRepository->exists(id: $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return __('messages.account.not_found_in_db');
    }
}
