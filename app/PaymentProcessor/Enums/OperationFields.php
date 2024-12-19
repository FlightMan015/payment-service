<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum OperationFields: string
{
    case PAYMENT_TYPE = 'payment_type';
    case REFERENCE_ID = 'reference_id';
    case TOKEN = 'token';
    case ACH_TOKEN = 'ach_token';
    case CC_EXP_YEAR = 'cc_exp_year';
    case CC_EXP_MONTH = 'cc_exp_month';
    case ACH_ACCOUNT_NUMBER = 'ach_account_number';
    case ACH_ROUTING_NUMBER = 'ach_routing_number';
    case ACH_ACCOUNT_TYPE = 'ach_account_type';
    case NAME_ON_ACCOUNT = 'name_on_account';
    case ADDRESS_LINE_1 = 'address_line_1';
    case ADDRESS_LINE_2 = 'address_line_2';
    case CITY = 'city';
    case EMAIL_ADDRESS = 'email_address';
    case PROVINCE = 'province';
    case POSTAL_CODE = 'postal_code';
    case COUNTRY_CODE = 'country_code';
    case AMOUNT = 'amount';
    case CHARGE_DESCRIPTION = 'charge_description';
    case TRANSACTION_ID = 'transaction_id';
    case REFERENCE_TRANSACTION_ID = 'reference_transaction_id';
    public const string PAYMENT_TYPE_REGEX = '^[0-9]{1}$';
    public const string REFERENCE_ID_REGEX = '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$';
    public const string TOKEN_REGEX = '^[a-zA-Z0-9_\-]+$';
    public const string ACH_TOKEN_REGEX = self::TOKEN_REGEX;
    public const string CC_EXP_YEAR_REGEX = '^[0-9]{2}$';
    public const string CC_EXP_MONTH_REGEX = '^([0]{0,1}[1-9]|1[0-2])$';
    public const string ACH_ACCOUNT_NUMBER_REGEX = '^[0-9A-Za-z\s]{5,17}$';
    public const string ACH_ROUTING_NUMBER_REGEX = '^[0-9]{9}$';
    public const string NAME_ON_ACCOUNT_REGEX = "^[^\`]+$";
    public const string ADDRESS_LINE_1_REGEX = "^[^\`]+$";
    public const string ADDRESS_LINE_2_REGEX = "^[^\`]+$";
    public const string CITY_REGEX = "^[^\`]+$";
    public const string EMAIL_ADDRESS_REGEX = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$';
    public const string PROVINCE_REGEX = '^[A-Z]{2}$';
    public const string POSTAL_CODE_REGEX = '^\d{5}([\-]?\d{4})?$';
    public const string COUNTRY_CODE_REGEX = '^[A-Z]{3}$';
    public const string TRANSACTION_ID_REGEX = '^[a-zA-Z0-9_-]+$';
    public const string REFERENCE_TRANSACTION_ID_REGEX = '^[a-zA-Z0-9_-]+$';

    /**
     * @return string
     */
    public function getter(): string
    {
        return 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $this->value)));
    }

    /**
     * @return string
     */
    public function setter(): string
    {
        return 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $this->value)));
    }

    /**
     * @param string|null $modifier
     *
     * @return string
     */
    public function regex(string|null $modifier = null): string
    {
        $constantName = strtoupper($this->value) . '_REGEX';

        if (!defined(self::class . '::' . $constantName)) {
            return '//';
        }

        return sprintf('/%s/%s', constant(name: self::class . '::' . $constantName), $modifier);
    }
}
