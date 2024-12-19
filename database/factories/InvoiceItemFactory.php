<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Database\Factories\Traits\WithoutRelationships;
use Database\Factories\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 *
 * @method InvoiceItem create(array $attributes = [])
 * @method InvoiceItem make(array $attributes = [])
 * @method InvoiceItem withoutRelationships()
 * @method InvoiceItem|Collection<int, InvoiceItem> makeWithRelationships(array $attributes = [], array $relationships = [])
 */
class InvoiceItemFactory extends Factory
{
    use WithoutRelationships;
    use WithRelationships;

    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->uuid(),
            'external_ref_id' => $this->faker->unique()->randomNumber(4),
            'is_taxable' => $this->faker->boolean(),
            'quantity' => $this->faker->randomNumber(1),
            'description' => $this->faker->sentence(),
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomNumber(2),
        ];
    }
}
