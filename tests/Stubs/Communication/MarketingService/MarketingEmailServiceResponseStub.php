<?php

declare(strict_types=1);

namespace Tests\Stubs\Communication\MarketingService;

class MarketingEmailServiceResponseStub
{
    /**
     * @return array
     */
    public static function sendSuccessResponse(): array
    {
        return [
            'status' => 202,
            'statusText' => 'Accepted',
            'config' => '{"template_id":"d-b58512a59e004580a63323c3925ad99a","personalizations":[{"to":[{"email":"test@goaptive.com"}],"reply_to":[{"email":"camemail@goaptive.com"}],"dynamic_template_data":{"first_name":"Test","last_name":"Person","phone_number":"8011112222","email_address":"test.person@goaptive.com","customer_id":"2080880","address":"12 NORTH 44 WEST STREET","city":"OREM","state":"UT","zip":"84057","cam_name":"Test Person","cam_email":"cam@goaptive.com","cam_phone_number":"8012223333","customer_autopay":"true","amount_due":"217.00","taxes":"17.58","total_due":"234.58","billed_amount":"234.58","credit_card_last_4":"1111"}}],"from":{"email":"camemail@goaptive.com","name":"Aptive Environmental"}}'
        ];
    }
}
