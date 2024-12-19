<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostPaymentRequest;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Carbon\Carbon;

class CreateCheckPaymentCommand
{
    private const int DEFAULT_APPLIED_AMOUNT = 0;

    /**
     * @param string $accountId
     * @param int $amount
     * @param PaymentTypeEnum $type
     * @param Carbon $checkDate
     * @param PaymentStatusEnum $paymentStatus
     * @param PaymentGatewayEnum $paymentGateway
     * @param int $appliedAmount
     * @param string|null $notes
     */
    public function __construct(
        public readonly string $accountId,
        public readonly int $amount,
        public readonly PaymentTypeEnum $type,
        public readonly Carbon $checkDate,
        public readonly PaymentStatusEnum $paymentStatus,
        public readonly PaymentGatewayEnum $paymentGateway,
        public readonly int $appliedAmount,
        public readonly string|null $notes,
    ) {
    }

    /**
     * @param string $accountId
     * @param int $amount
     * @param string $type
     * @param Carbon $checkDate
     * @param PaymentStatusEnum|null $paymentStatus
     * @param PaymentGatewayEnum|null $paymentGateway
     * @param int|null $appliedAmount
     * @param string|null $notes
     *
     * @return self
     */
    public static function create(
        string $accountId,
        int $amount,
        string $type,
        Carbon $checkDate,
        PaymentStatusEnum|null $paymentStatus = null,
        PaymentGatewayEnum|null $paymentGateway = null,
        int|null $appliedAmount = null,
        string|null $notes = null,
    ): self {
        return new self(
            accountId: $accountId,
            amount: $amount,
            type: PaymentTypeEnum::fromName($type),
            checkDate: $checkDate,
            paymentStatus: $paymentStatus ?? PaymentStatusEnum::CAPTURED,
            paymentGateway: $paymentGateway ?? PaymentGatewayEnum::CHECK,
            appliedAmount: $appliedAmount ?? self::DEFAULT_APPLIED_AMOUNT,
            notes: $notes,
        );
    }

    /**
     * @param PostPaymentRequest $request
     *
     * @return self
     */
    public static function fromRequest(PostPaymentRequest $request): self
    {
        return self::create(
            accountId: $request->input(key: 'account_id'),
            amount:  $request->integer(key: 'amount'),
            type: $request->input(key: 'type'),
            checkDate: $request->date(key: 'check_date'),
            notes: $request->get(key: 'notes'),
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'amount' => $this->amount,
            'payment_type_id' => $this->type->value,
            'processed_at' => $this->checkDate,
            'payment_status_id' => $this->paymentStatus->value,
            'payment_gateway_id' => $this->paymentGateway->value,
            'applied_amount' => $this->appliedAmount,
            'notes' => $this->notes,
        ];
    }
}
