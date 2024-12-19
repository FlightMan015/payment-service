<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Api\Exceptions\PaymentValidationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractRequest extends FormRequest
{
    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     *
     * @throws PaymentValidationException
     *
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new PaymentValidationException(message: __('messages.invalid_input'), errors: $validator->errors()->all());
    }

    protected function regex(string $expression, string|null $modifier = null): string
    {
        return 'regex:/' . $expression . '/' . $modifier;
    }

    /**
     * Array of rules for validation
     *
     * @return array
     */
    abstract public function rules(): array;
}
