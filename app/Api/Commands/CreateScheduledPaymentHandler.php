<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Events\PaymentScheduledEvent;
use App\Exceptions\ScheduledPaymentDuplicateException;
use App\Factories\ScheduledPaymentTriggerMetadataValidatorFactory;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use Illuminate\Support\Facades\Log;

class CreateScheduledPaymentHandler
{
    private CreateScheduledPaymentCommand $command;

    /**
     * @param ScheduledPaymentRepository $scheduledPaymentRepository
     */
    public function __construct(private readonly ScheduledPaymentRepository $scheduledPaymentRepository)
    {
    }

    /**
     * @param CreateScheduledPaymentCommand $createScheduledPaymentCommand
     *
     * @return string
     */
    public function handle(CreateScheduledPaymentCommand $createScheduledPaymentCommand): string
    {
        $this->command = $createScheduledPaymentCommand;
        $this->logPaymentDetails();
        $this->validateMetadata();
        $this->validatePaymentDuplication();

        $scheduledPayment = $this->savePayment();
        PaymentScheduledEvent::dispatch($scheduledPayment);
        Log::info(message: __('messages.scheduled_payment.create.created', ['id' => $scheduledPayment->id]));

        return $scheduledPayment->id;
    }

    private function logPaymentDetails(): void
    {
        Log::info(
            __('messages.scheduled_payment.create.creating', ['payment_method_id' => $this->command->paymentMethodId]),
            $this->command->toArray()
        );
    }

    private function validateMetadata(): void
    {
        $validator = ScheduledPaymentTriggerMetadataValidatorFactory::make($this->command->trigger);
        $validator->validate($this->command->metadata);
    }

    private function validatePaymentDuplication(): void
    {
        $duplicatePayment = $this->scheduledPaymentRepository->findDuplicate(
            accountId: $this->command->accountId,
            paymentMethodId: $this->command->paymentMethodId,
            trigger: $this->command->trigger,
            amount: $this->command->amount,
            metadata: $this->command->metadata
        );

        if (!is_null($duplicatePayment)) {
            throw new ScheduledPaymentDuplicateException(duplicatePaymentId: $duplicatePayment->id);
        }
    }

    private function savePayment(): ScheduledPayment
    {
        return $this->scheduledPaymentRepository->create(attributes: [
            'account_id' => $this->command->accountId,
            'amount' => $this->command->amount,
            'payment_method_id' => $this->command->paymentMethodId,
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'trigger_id' => $this->command->trigger->value,
            'metadata' => $this->command->metadata,
        ]);
    }
}
