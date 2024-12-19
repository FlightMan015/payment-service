<?php

declare(strict_types=1);

namespace Database\Factories\CRM\FieldOperations;

use App\Models\CRM\FieldOperations\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 *
 * @method Area create(array $attributes = [])
 * @method Area make(array $attributes = [])
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    /**
     * @return array
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->randomNumber(9),
            'external_ref_id' => $this->faker->unique()->randomNumber(5),
            'market_id' => $this->faker->randomNumber(9),
            'name' => $this->faker->name(),
            'is_active' => $this->faker->boolean(),
            'timezone' => $this->faker->timezone(),
            'license_number' => $this->faker->word(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'zip' => $this->faker->postcode(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'website' => $this->faker->url(),
            'caution_statements' => $this->faker->text(),
        ];
    }

    /**
     * Define a state for active areas.
     *
     * @return AreaFactory
     */
    public function active(): AreaFactory
    {
        return $this->state(['is_active' => true]);
    }
}
