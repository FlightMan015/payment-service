<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\PaymentProcessor\Enums\TokenexTokenSchemeEnum;
use Illuminate\Validation\Rule;

/**
 * @property string $token_scheme
 * @property array $origins
 */
class PostTokenexGenerateAuthenticationKeyRequest extends AbstractRequest
{
    /**
     * @return array<string, array<Rule|string>>
     */
    public function rules(): array
    {
        return [
            'token_scheme' => [
                'required',
                Rule::in(array_column(TokenexTokenSchemeEnum::cases(), 'value'))
            ],
            'origins' => [
                'required',
                'array',
                'min:1'
            ],
            'origins.*' => [
                'required',
                'string',
                'url:http,https',
                'distinct'
            ],
            'timestamp' => [
                'required',
                'date_format:YmdHis'
            ]
        ];
    }
}
