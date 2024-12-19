<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Operations\AbstractOperation;

abstract class AbstractValidator
{
    protected static array $fields = [];
    protected static array $oneOfThese = [];
    /** @var array $formatCheckFields stores fields we want to validate format on, but they could be nullable */
    protected static array $formatCheckFields = [];
    protected array $errors = [];
    protected AbstractOperation $operation;

    /**
     * @param AbstractOperation $operation
     */
    public function __construct(AbstractOperation $operation)
    {
        $this->operation = $operation;
    }

    /**
     * @return bool
     */
    public function validate(): bool
    {
        $result = $this->checkIsSet(members: static::$fields);
        $result &= $this->checkOneOfThese(members: static::$oneOfThese);
        $result &= $this->checkFormat(members: static::$fields);
        $result &= $this->checkFormat(members: static::$formatCheckFields, isEmptyValueAllowed: true);

        return $result > 0;
    }

    /**
     * @param string $error
     *
     * @return void
     */
    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param OperationFields[]|null $members
     * @param bool $addError
     *
     * @return bool
     */
    protected function checkIsSet(array|null $members, bool $addError = true): bool
    {
        $result = true;

        foreach ($members as $member) {
            $getterName = $member->getter();
            $testResult = !empty($this->operation->{$getterName}());

            if (!$testResult) {
                $result = false;

                if ($addError) {
                    $this->addError(error: __('messages.operation.validation.required_field_missing', ['field' => $member->value]));
                }
            }
        }

        return $result;
    }

    protected function checkOneOfThese(array $members): bool
    {
        $checkResult = true;

        $oneOfTheseFound = false;
        $memberThatWasFound = null;

        if (empty($members)) {
            return true;
        }

        foreach ($members as $member) {
            $memberAllSet = $this->checkIsSet(members: $member, addError: false);

            if ($memberAllSet) {
                if (!$oneOfTheseFound) {
                    $oneOfTheseFound = true;
                    $memberThatWasFound = $member;
                    continue;
                }
                $this->addError(__('messages.operation.validation.both_ach_and_cc_set'));
                $checkResult = false;

            }
        }

        if (!$oneOfTheseFound) {
            $this->addError(error: __('messages.operation.validation.no_payment_info_provided'));

            return false;
        }

        return $checkResult && $this->checkIsSet(members: $memberThatWasFound);
    }

    /**
     * @param OperationFields[] $members
     * @param bool $isEmptyValueAllowed
     *
     * @return bool
     */
    protected function checkFormat(array $members, bool $isEmptyValueAllowed = false): bool
    {
        $result = true;

        foreach ($members as $member) {
            if ($isEmptyValueAllowed && empty($this->operation->{$member->getter()}())) {
                continue;
            }

            if ($member !== OperationFields::AMOUNT) {
                $regex = $member->regex(modifier: $member === OperationFields::NAME_ON_ACCOUNT ? 'u' : null);

                $testResult = (bool)preg_match(
                    pattern: $regex,
                    subject: $this->operation->{$member->getter()}() ?? ''
                );

                if (!$testResult) {
                    $result = false;
                    $this->addError(
                        error: __('messages.operation.validation.field_does_not_meet_format', ['field' => $member->value, 'regex' => $regex])
                    );
                }
            } elseif (0 > $this->operation->getAmount()?->getAmount()) {
                $result = false;
                $this->addError(error: __('messages.operation.validation.field_cannot_be_negative', ['field' => $member->value]));
            }
        }

        return $result;
    }
}
