<?php

declare(strict_types=1);

namespace Tests\Stubs\PaymentProcessor;

use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;

class WorldpayResponseStub
{
    /**
     * @param int|string $transactionId
     *
     * @return string
     */
    public static function authCaptureSuccess(int|string $transactionId = '202309250413591'): string
    {
        return <<<XML
<CreditCardSaleResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Approved</ExpressResponseMessage>
        <HostResponseCode>000</HostResponseCode>
        <HostResponseMessage>AP</HostResponseMessage>
        <ExpressTransactionDate>20230925</ExpressTransactionDate>
        <ExpressTransactionTime>041359</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <Batch>
            <HostBatchID>24</HostBatchID>
            <HostItemID>927</HostItemID>
            <HostBatchAmount>1010290.57</HostBatchAmount>
        </Batch>
        <Card>
            <AVSResponseCode>N</AVSResponseCode>
            <CVVResponseCode>M</CVVResponseCode>
            <ExpirationMonth>12</ExpirationMonth>
            <ExpirationYear>25</ExpirationYear>
            <CardLogo>Visa</CardLogo>
            <CardNumberMasked>xxxx-xxxx-xxxx-0076</CardNumberMasked>
            <BIN>476173</BIN>
        </Card>
        <Transaction>
            <TransactionID>291916655</TransactionID>
            <ApprovalNumber>000013</ApprovalNumber>
            <ReferenceNumber>123456</ReferenceNumber>
            <AcquirerData>aVb001234567810425c0425d5e00</AcquirerData>
            <ProcessorName>NULL_PROCESSOR_TEST</ProcessorName>
            <TransactionStatus>Approved</TransactionStatus>
            <TransactionStatusCode>1</TransactionStatusCode>
            <ApprovedAmount>32.00</ApprovedAmount>
            <NetworkTransactionID>{$transactionId}</NetworkTransactionID>
        </Transaction>
    </Response>
</CreditCardSaleResponse>
XML;
    }

    /**
     * @param string|null $errorMessage
     * @param string $errorCode
     *
     * @return string
     */
    public static function authCaptureUnsuccess(string|null $errorMessage, string $errorCode = '101'): string
    {
        if (is_null($errorMessage)) {
            $errorMessage = 'TransactionAmount invalid';
        }
        return <<<XML
<CreditCardSaleResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>{$errorCode}</ExpressResponseCode>
        <ExpressResponseMessage>{$errorMessage}</ExpressResponseMessage>
        <Card>
            <ExpirationMonth>12</ExpirationMonth>
            <ExpirationYear>25</ExpirationYear>
        </Card>
        <Transaction>
            <ReferenceNumber>123456</ReferenceNumber>
        </Transaction>
    </Response>
</CreditCardSaleResponse>
XML;
    }

    /**
     * @return string
     */
    public static function cancelSuccess(): string
    {
        return <<<XML
<CreditCardReversalResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <HostResponseCode>006</HostResponseCode>
        <HostResponseMessage>REVERSED</HostResponseMessage>
        <ExpressTransactionDate>20230515</ExpressTransactionDate>
        <ExpressTransactionTime>053753</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <Card>
            <CardLogo>Visa</CardLogo>
            <CardNumberMasked>xxxx-xxxx-xxxx-0076</CardNumberMasked>
            <BIN>476173</BIN>
        </Card>
        <Transaction>
            <TransactionID>238397589</TransactionID>
            <ApprovalNumber>000035</ApprovalNumber>
            <ReferenceNumber>23051501</ReferenceNumber>
            <AcquirerData>aVb001234567810425c0425d5e00</AcquirerData>
            <ProcessorName>NULL_PROCESSOR_TEST</ProcessorName>
            <TransactionStatus>Success</TransactionStatus>
            <TransactionStatusCode>8</TransactionStatusCode>
        </Transaction>
        <Terminal>
            <MotoECICode>0</MotoECICode>
        </Terminal>
    </Response>
</CreditCardReversalResponse>
XML;
    }

    /**
     * @return string
     */
    public static function creditSuccess(): string
    {
        return <<<XML
<CreditCardReturnResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Approved</ExpressResponseMessage>
        <HostResponseCode>000</HostResponseCode>
        <HostResponseMessage>AP</HostResponseMessage>
        <ExpressTransactionDate>20230515</ExpressTransactionDate>
        <ExpressTransactionTime>055533</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <Batch>
            <HostBatchID>21</HostBatchID>
            <HostItemID>151</HostItemID>
            <HostBatchAmount>7936.18</HostBatchAmount>
        </Batch>
        <Card>
            <CardLogo>Visa</CardLogo>
            <CardNumberMasked>xxxx-xxxx-xxxx-0076</CardNumberMasked>
            <BIN>476173</BIN>
        </Card>
        <Transaction>
            <TransactionID>238403843</TransactionID>
            <ApprovalNumber>000055</ApprovalNumber>
            <ReferenceNumber>23051502</ReferenceNumber>
            <ProcessorName>NULL_PROCESSOR_TEST</ProcessorName>
            <TransactionStatus>Approved</TransactionStatus>
            <TransactionStatusCode>1</TransactionStatusCode>
        </Transaction>
    </Response>
</CreditCardReturnResponse>
XML;
    }

    /**
     * @param bool $isReturned
     * @param bool $isError
     * @param bool $isSettled
     *
     * @return string
     */
    public static function statusSuccess(
        bool $isReturned = false,
        bool $isError = false,
        bool $isSettled = false,
    ): string {
        $transactionStatus = 'AuthCompleted';
        $transactionStatusCode = 6;
        if ($isReturned) {
            $transactionStatus = 'Returned';
            $transactionStatusCode = WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_RETURNED->value;
        }
        if ($isError) {
            $transactionStatus = 'Error';
            $transactionStatusCode = WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_ERROR->value;
        }
        if ($isSettled) {
            $transactionStatus = 'Settled';
            $transactionStatusCode = WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_SETTLED->value;
        }
        return <<<XML
<TransactionQueryResponse
    xmlns='https://reporting.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <ExpressTransactionDate>20230510</ExpressTransactionDate>
        <ExpressTransactionTime>073830</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <ReportingData>
            <Items>
                <Item>
                    <TransactionID>234791851</TransactionID>
                    <AcceptorID>364802417</AcceptorID>
                    <AccountID>1237254</AccountID>
                    <Name>DevEx User</Name>
                    <TerminalID>01</TerminalID>
                    <ApplicationID>15684</ApplicationID>
                    <ApprovalNumber>271102</ApprovalNumber>
                    <ApprovedAmount>10.43</ApprovedAmount>
                    <AVSResponseCode>Y    </AVSResponseCode>
                    <ExpirationMonth>02</ExpirationMonth>
                    <ExpirationYear>24</ExpirationYear>
                    <ExpressResponseCode>0</ExpressResponseCode>
                    <ExpressResponseMessage>Approved</ExpressResponseMessage>
                    <HostBatchID>1</HostBatchID>
                    <HostResponseCode>00</HostResponseCode>
                    <OriginalAuthorizedAmount>10.43</OriginalAuthorizedAmount>
                    <ReferenceNumber>some-ref-id-1</ReferenceNumber>
                    <TicketNumber>some-ref-id-1</TicketNumber>
                    <TrackingID>88562B8BA37F485080675336511DCC49</TrackingID>
                    <TransactionAmount>10.43</TransactionAmount>
                    <TransactionStatus>$transactionStatus</TransactionStatus>
                    <TransactionStatusCode>$transactionStatusCode</TransactionStatusCode>
                    <TransactionType>CreditCardAuthorization</TransactionType>
                    <CardNumberMasked>xxxx-xxxx-xxxx-4444</CardNumberMasked>
                    <CardLogo>Mastercard</CardLogo>
                    <CardType>Credit</CardType>
                    <TrackDataPresent>FALSE</TrackDataPresent>
                    <HostTransactionID>090000</HostTransactionID>
                    <BillingName>Test User</BillingName>
                    <BillingZipCode>12345</BillingZipCode>
                    <ExpressTransactionDate>20230508</ExpressTransactionDate>
                    <ExpressTransactionTime>072745</ExpressTransactionTime>
                    <TimeStamp>2023-05-08T07:27:45.967</TimeStamp>
                    <LaneNumber>01</LaneNumber>
                    <IntegrationTypeID>1</IntegrationTypeID>
                    <SystemTraceAuditNumber>791851</SystemTraceAuditNumber>
                    <RetrievalReferenceNumber>312807791851</RetrievalReferenceNumber>
                    <TerminalData>|3|0|0|0|0|0|0|||2|0|9|1|0|0|2|9|2|0|0|0|0|0|0||| |||</TerminalData>
                    <MerchantCategoryCode>5555</MerchantCategoryCode>
                    <CardInputCode>0</CardInputCode>
                </Item>
            </Items>
        </ReportingData>
        <ReportingID>321972662</ReportingID>
    </Response>
</TransactionQueryResponse>
XML;
    }

    /**
     * @return string
     */
    public static function authorizeSuccess(): string
    {
        return <<<XML
<CreditCardAuthorizationResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Approved</ExpressResponseMessage>
        <HostResponseCode>000</HostResponseCode>
        <HostResponseMessage>AP</HostResponseMessage>
        <ExpressTransactionDate>20230515</ExpressTransactionDate>
        <ExpressTransactionTime>053522</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <Batch>
            <HostBatchID>21</HostBatchID>
        </Batch>
        <Card>
            <AVSResponseCode>N</AVSResponseCode>
            <CVVResponseCode>M</CVVResponseCode>
            <ExpirationMonth>12</ExpirationMonth>
            <ExpirationYear>22</ExpirationYear>
            <CardLogo>Visa</CardLogo>
            <CardNumberMasked>xxxx-xxxx-xxxx-0076</CardNumberMasked>
            <BIN>476173</BIN>
        </Card>
        <Transaction>
            <TransactionID>238396722</TransactionID>
            <ApprovalNumber>000035</ApprovalNumber>
            <ReferenceNumber>23051501</ReferenceNumber>
            <AcquirerData>aVb001234567810425c0425d5e00</AcquirerData>
            <ProcessorName>NULL_PROCESSOR_TEST</ProcessorName>
            <TransactionStatus>Authorized</TransactionStatus>
            <TransactionStatusCode>5</TransactionStatusCode>
            <ApprovedAmount>1.00</ApprovedAmount>
            <NetworkTransactionID>202305150535221</NetworkTransactionID>
        </Transaction>
        <Terminal>
            <MotoECICode>0</MotoECICode>
        </Terminal>
    </Response>
</CreditCardAuthorizationResponse>
XML;
    }

    /**
     * @param string $errorMessage
     *
     * @return string
     */
    public static function authorizeUnsuccessful(string $errorMessage = 'PAYMENT ACCOUNT NOT FOUND'): string
    {
        return <<<XML
<CreditCardAuthorizationResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>103</ExpressResponseCode>
        <ExpressResponseMessage>$errorMessage</ExpressResponseMessage>
        <ExpressTransactionDate>20230807</ExpressTransactionDate>
        <ExpressTransactionTime>085856</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <Batch>
            <HostBatchID>1</HostBatchID>
        </Batch>
        <Transaction>
            <ReferenceNumber>19702</ReferenceNumber>
            <ProcessorName>NULL_PROCESSOR_TEST</ProcessorName>
        </Transaction>
        <Address>
            <BillingAddress1>13613 Urban Ramp Suite</BillingAddress1>
            <BillingZipcode>93731-7062</BillingZipcode>
        </Address>
        <Terminal>
            <MotoECICode>0</MotoECICode>
        </Terminal>
    </Response>
</CreditCardAuthorizationResponse>
XML;
    }

    /**
     * @return string
     */
    public static function captureSuccess(): string
    {
        return <<<XML
<CreditCardAuthorizationCompletionResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <HostResponseCode>000</HostResponseCode>
        <HostResponseMessage>AP</HostResponseMessage>
        <ExpressTransactionDate>20230515</ExpressTransactionDate>
        <ExpressTransactionTime>053709</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <Batch>
            <HostBatchID>21</HostBatchID>
            <HostItemID>149</HostItemID>
            <HostBatchAmount>7936.18</HostBatchAmount>
        </Batch>
        <Card>
            <ExpirationMonth>12</ExpirationMonth>
            <ExpirationYear>22</ExpirationYear>
            <CardLogo>Visa</CardLogo>
            <CardNumberMasked>xxxx-xxxx-xxxx-0076</CardNumberMasked>
            <BIN>476173</BIN>
        </Card>
        <Transaction>
            <TransactionID>238397346</TransactionID>
            <ApprovalNumber>000035</ApprovalNumber>
            <ReferenceNumber>23051501</ReferenceNumber>
            <AcquirerData>aVb001234567810425c0425d5e00</AcquirerData>
            <ProcessorName>NULL_PROCESSOR_TEST</ProcessorName>
            <TransactionStatus>Approved</TransactionStatus>
            <TransactionStatusCode>1</TransactionStatusCode>
        </Transaction>
        <Terminal>
            <TerminalData>0|0|0|0|2|0|0|0|1||2|0|9|5|0|0|0|9|2|0|0|0|0|2|0||| |||</TerminalData>
        </Terminal>
    </Response>
</CreditCardAuthorizationCompletionResponse>
XML;
    }

    /**
     * @param string $paymentBrand
     *
     * @return string
     */
    public static function paymentAccountQuerySuccess(string $paymentBrand = 'Amex'): string
    {
        return <<<XML
<PaymentAccountQueryResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <ExpressTransactionDate>20230810</ExpressTransactionDate>
        <ExpressTransactionTime>041653</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <QueryData>
            <Items>
                <Item>
                    <PaymentAccountID>XXXXXX-XXXXX-XXXXX-XXXXX-XXXXXXX</PaymentAccountID>
                    <PaymentAccountType>0</PaymentAccountType>
                    <TruncatedCardNumber>xxxxxxxxxxx0126</TruncatedCardNumber>
                    <ExpirationMonth>05</ExpirationMonth>
                    <ExpirationYear>26</ExpirationYear>
                    <PaymentAccountReferenceNumber>5076884</PaymentAccountReferenceNumber>
                    <BillingName>test customar947 billing name</BillingName>
                    <BillingAddress1>test customar947 billing address</BillingAddress1>
                    <BillingCity>test customar947 billing city</BillingCity>
                    <BillingState>GA</BillingState>
                    <BillingZipcode>12354</BillingZipcode>
                    <ShippingZipcode>12354</ShippingZipcode>
                    <PaymentBrand>{$paymentBrand}</PaymentBrand>
                    <PASSUpdaterBatchStatus>0</PASSUpdaterBatchStatus>
                    <PASSUpdaterStatus>14</PASSUpdaterStatus>
                    <TokenProviderID>0</TokenProviderID>
                </Item>
            </Items>
        </QueryData>
        <ServicesID>331396958</ServicesID>
        <PaymentAccount>
            <PaymentAccountID>C3EC7424-C2D7-4DC3-827D-2E6401EEEF0C</PaymentAccountID>
        </PaymentAccount>
    </Response>
</PaymentAccountQueryResponse>
XML;
    }

    /**
     * @return string
     */
    public static function paymentAccountQueryNotFound(): string
    {
        return <<<XML
<PaymentAccountQueryResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>101</ExpressResponseCode>
        <ExpressResponseMessage>Invalid PaymentAccountID</ExpressResponseMessage>
        <PaymentAccount>
            <PaymentAccountID>C3EC7424-C2D7-4DC3-827D-2E6401EEEF023</PaymentAccountID>
        </PaymentAccount>
    </Response>
</PaymentAccountQueryResponse>
XML;
    }

    /**
     * @param bool $withExpirationData
     * @param bool $withEmptyExpirationData
     *
     * @return string
     */
    public static function getPaymentAccountSuccess(bool $withExpirationData = false, bool $withEmptyExpirationData = false): string
    {
        if ($withExpirationData) {
            return <<<XML
<PaymentAccountQueryResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <ExpressTransactionDate>20240301</ExpressTransactionDate>
        <ExpressTransactionTime>090930</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-06:00:00</ExpressTransactionTimezone>
        <QueryData>
            <Items>
                <Item>
                    <PaymentAccountID>485b00a2-7988-4d56-a70e-ea349898dcfc</PaymentAccountID>
                    <PaymentAccountType>0</PaymentAccountType>
                    <TruncatedCardNumber>xxxxxxxxxxxx2393</TruncatedCardNumber>
                    <ExpirationMonth>02</ExpirationMonth>
                    <ExpirationYear>27</ExpirationYear>
                    <PaymentAccountReferenceNumber>5077790</PaymentAccountReferenceNumber>
                    <BillingName>Ivan Refund Test</BillingName>
                    <BillingAddress1>Address</BillingAddress1>
                    <BillingCity>City</BillingCity>
                    <BillingState>UT</BillingState>
                    <BillingZipcode>01103</BillingZipcode>
                    <PaymentBrand>Mastercard</PaymentBrand>
                    <PASSUpdaterBatchStatus>0</PASSUpdaterBatchStatus>
                    <PASSUpdaterStatus>14</PASSUpdaterStatus>
                </Item>
            </Items>
        </QueryData>
        <ServicesID>374731489</ServicesID>
        <PaymentAccount>
            <PaymentAccountID>485B00a2-7988-4D56-A70E-EA349898DCFC</PaymentAccountID>
        </PaymentAccount>
    </Response>
</PaymentAccountQueryResponse>
XML;
        }

        if ($withEmptyExpirationData) {
            return <<<XML
<PaymentAccountQueryResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <ExpressTransactionDate>20240301</ExpressTransactionDate>
        <ExpressTransactionTime>090930</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-06:00:00</ExpressTransactionTimezone>
        <QueryData>
            <Items>
                <Item>
                    <PaymentAccountID>485b00a2-7988-4d56-a70e-ea349898dcfc</PaymentAccountID>
                    <PaymentAccountType>0</PaymentAccountType>
                    <TruncatedCardNumber>xxxxxxxxxxxx2393</TruncatedCardNumber>
                    <ExpirationMonth>02</ExpirationMonth>
                    <ExpirationYear/>
                    <PaymentAccountReferenceNumber>5077790</PaymentAccountReferenceNumber>
                    <BillingName>Ivan Refund Test</BillingName>
                    <BillingAddress1>Address</BillingAddress1>
                    <BillingCity>City</BillingCity>
                    <BillingState>UT</BillingState>
                    <BillingZipcode>01103</BillingZipcode>
                    <PaymentBrand>Mastercard</PaymentBrand>
                    <PASSUpdaterBatchStatus>0</PASSUpdaterBatchStatus>
                    <PASSUpdaterStatus>14</PASSUpdaterStatus>
                </Item>
            </Items>
        </QueryData>
        <ServicesID>374731489</ServicesID>
        <PaymentAccount>
            <PaymentAccountID>485B00a2-7988-4D56-A70E-EA349898DCFC</PaymentAccountID>
        </PaymentAccount>
    </Response>
</PaymentAccountQueryResponse>
XML;
        }

        return <<<XML
<PaymentAccountQueryResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <ExpressTransactionDate>20230905</ExpressTransactionDate>
        <ExpressTransactionTime>070056</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <QueryData>
            <Items>
                <Item>
                    <PaymentAccountID>a6884e64-d32a-45b3-ab6d-3618dc307415</PaymentAccountID>
                    <PaymentAccountType>3</PaymentAccountType>
                    <TruncatedAccountNumber>xxxx1111</TruncatedAccountNumber>
                    <TruncatedRoutingNumber>xxxx1111</TruncatedRoutingNumber>
                    <PaymentAccountReferenceNumber>5076797</PaymentAccountReferenceNumber>
                    <PaymentBrand>Visa</PaymentBrand>
                    <ExpirationYear>27</ExpirationYear>
                    <PASSUpdaterBatchStatus>0</PASSUpdaterBatchStatus>
                    <PASSUpdaterStatus>14</PASSUpdaterStatus>
                </Item>
            </Items>
        </QueryData>
        <ServicesID>336693863</ServicesID>
        <PaymentAccount>
            <PaymentAccountID>A6884E64-D32A-45B3-AB6D-3618DC307415</PaymentAccountID>
        </PaymentAccount>
    </Response>
</PaymentAccountQueryResponse>
XML;
    }

    /**
     * @return string
     */
    public static function getPaymentAccountUnsuccess(): string
    {
        return <<<XML
<PaymentAccountQueryResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>101</ExpressResponseCode>
        <ExpressResponseMessage>PaymentAccount record(s) not found</ExpressResponseMessage>
        <ExpressTransactionDate>20230905</ExpressTransactionDate>
        <ExpressTransactionTime>092848</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <ServicesID>336747682</ServicesID>
        <PaymentAccount>
            <PaymentAccountID>A6884E64-1234-45B3-AB6D-3618DC307415</PaymentAccountID>
        </PaymentAccount>
    </Response>
</PaymentAccountQueryResponse>
XML;
    }

    /**
     * @return string
     */
    public static function updatePaymentAccountSuccess(): string
    {
        return <<<XML
<PaymentAccountUpdateResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>PaymentAccount updated</ExpressResponseMessage>
        <ExpressTransactionDate>20230831</ExpressTransactionDate>
        <ExpressTransactionTime>082046</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <ServicesID>335623411</ServicesID>
        <PaymentAccount>
            <PaymentAccountID>c3ec7424-c2d7-4dc3-827d-2e6401eeef0c</PaymentAccountID>
            <PaymentAccountReferenceNumber>5076884</PaymentAccountReferenceNumber>
        </PaymentAccount>
    </Response>
</PaymentAccountUpdateResponse>
XML;
    }

    /**
     * @return string
     */
    public static function updatePaymentAccountUnsuccess(): string
    {
        return <<<XML
<PaymentAccountUpdateResponse xmlns='https://services.elementexpress.com'>
    <Response>
        <ExpressResponseCode>101</ExpressResponseCode>
        <ExpressResponseMessage>Invalid PaymentAccountID</ExpressResponseMessage>
        <PaymentAccount>
            <PaymentAccountID>c3ec7424-c2d7-4dc3-827d-2e6401eeef0c12</PaymentAccountID>
            <PaymentAccountReferenceNumber>5076884</PaymentAccountReferenceNumber>
        </PaymentAccount>
    </Response>
</PaymentAccountUpdateResponse>
XML;
    }

    /**
     * @param string $transactionSetupId
     *
     * @return string
     */
    public static function createTransactionSetupSuccess(string $transactionSetupId = 'D5BEEABF-2B6F-491F-8191-9FD77455CF86'): string
    {
        return <<<XML
<TransactionSetupResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>0</ExpressResponseCode>
        <ExpressResponseMessage>Success</ExpressResponseMessage>
        <ExpressTransactionDate>20230915</ExpressTransactionDate>
        <ExpressTransactionTime>064904</ExpressTransactionTime>
        <ExpressTransactionTimezone>UTC-05:00:00</ExpressTransactionTimezone>
        <Transaction>
            <TransactionSetupID>{$transactionSetupId}</TransactionSetupID>
        </Transaction>
        <PaymentAccount>
            <TransactionSetupID>{$transactionSetupId}</TransactionSetupID>
        </PaymentAccount>
        <TransactionSetup>
            <TransactionSetupID>{$transactionSetupId}</TransactionSetupID>
            <ValidationCode>6285DAA0326A4835</ValidationCode>
        </TransactionSetup>
    </Response>
</TransactionSetupResponse>
XML;
    }

    /**
     * @return string
     */
    public static function createTransactionSetupUnsuccess(): string
    {
        return <<<XML
<TransactionSetupResponse xmlns='https://transaction.elementexpress.com'>
    <Response>
        <ExpressResponseCode>101</ExpressResponseCode>
        <ExpressResponseMessage>TransactionSetupMethod required</ExpressResponseMessage>
    </Response>
</TransactionSetupResponse>
XML;
    }
}
