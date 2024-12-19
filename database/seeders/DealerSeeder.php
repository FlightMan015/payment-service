<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CRM\Organization\Dealer;
use Illuminate\Database\Seeder;

class DealerSeeder extends Seeder
{
    /**
     * Seed transaction types.
     *
     * @return void
     */
    public function run(): void
    {
        Dealer::insertOrIgnore(values: [
            ['id' => 1, 'name' => 'Internal'],
        ]);
    }
}
