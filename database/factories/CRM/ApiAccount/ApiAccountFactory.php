<?php

declare(strict_types=1);

namespace Database\Factories\CRM\ApiAccount;

use App\Models\CRM\ApiAccount\ApiAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiAccount>
 *
 * @method ApiAccount create(array $attributes = [])
 * @method ApiAccount make(array $attributes = [])
 */
class ApiAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ApiAccount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
