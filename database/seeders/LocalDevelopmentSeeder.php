<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CRM\ApiAccount\ApiAccount;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Ledger;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use Illuminate\Database\Seeder;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // To tests API by using Postman
        $apiAccount = ApiAccount::factory()->create();

        // Customer related data
        $area = Area::whereExternalRefId(39)->first() ?: Area::factory()->create(['external_ref_id' => 39]);
        $account = Account::factory()->for($area)->hasInvoices(3)->create();

        // Payment method & Ledger
        $ccWorldpayPaymentMethod = PaymentMethod::factory()->cc()->for($account)->create([
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'cc_token' => 'ADA3B726-6BC3-4BE4-BEF1-BF495C391D24',
        ]);
        $achWorldpayPaymentMethod = PaymentMethod::factory()->ach()->for($account)->create([
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'ach_account_number_encrypted' => '11005490',
            'ach_routing_number' => 454487237,
            'cc_token' => null,
        ]);
        $tokenexPaymentMethod = PaymentMethod::factory()->cc()->for($account)->create([
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT->value,
            'cc_token' => '4111110NfzBk1111'
        ]);
        Ledger::factory()->for($account)->create([
            'autopay_payment_method_id' => $ccWorldpayPaymentMethod->id,
            'balance' => 1000
        ]);
    }
}
