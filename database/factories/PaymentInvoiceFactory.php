<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentInvoice;
use Database\Factories\Traits\WithoutRelationships;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentInvoice>
 *
 * @method static PaymentInvoice create(array $attributes = [])
 * @method static PaymentInvoice make(array $attributes = [])
 * @method PaymentInvoice withoutRelationships()
 */
class PaymentInvoiceFactory extends Factory
{
    use WithoutRelationships;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomNumber(nbDigits: 5), // Amount stored as cents in DB
        ];
    }
}
