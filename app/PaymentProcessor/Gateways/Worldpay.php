<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Gateways;

use App\Helpers\JsonDecoder;
use App\Helpers\SodiumEncryptHelper;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Enums\WorldpayMarketCodeEnum;
use App\PaymentProcessor\Enums\WorldpayPaymentAccountTypeEnum;
use App\PaymentProcessor\Enums\WorldpayRequestEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use App\PaymentProcessor\Enums\WorldpayReversalTypeEnum;
use App\PaymentProcessor\Enums\WorldpayTransactionMethodEnum;
use App\PaymentProcessor\Exceptions\GatewayDeclineReasonUnmapped;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\Traits\ConvertsArrayToXml;
use Aptive\Component\Money\MoneyHandler;
use Aptive\Worldpay\CredentialsRepository\Credentials\Credentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Money\Money;
use Psr\Log\LoggerInterface;

class Worldpay extends AbstractGateway implements GatewayInterface
{
    use ConvertsArrayToXml;

    private array $parsedResponse;
    private string|null $transactionResponseId;
    private string $transactionResponseCode;
    private array|null $transactionResponseData = null;
    private const string LANE_NUMBER = '01';
    private const string CARD_PRESENT_NOT_PRESENT = '3';
    private const string APPLICATION_VERSION = '0.1';
    private const string SEC_WEB = 'WEB';

    private function __construct(
        private Client $guzzle,
        private readonly int $worldpayAccountId,
        private readonly string $worldpayAccountToken,
        private readonly int $worldpayAcceptorId,
        private readonly int $worldpayApplicationId,
        private readonly string $worldpayApplicationName,
        private readonly string $worldpayTerminalId,
        private readonly bool $isProduction,
        protected LoggerInterface|null $logger,
    ) {
        $this->setLogger(logger: $this->logger);
    }

    /**
     * @param Credentials $credentials
     *
     * @throws BindingResolutionException
     *
     * @return self
     */
    public static function make(Credentials $credentials): self
    {
        return new self(
            guzzle: app()->make(abstract: Client::class),
            worldpayAccountId: (int)$credentials->validation()->accountId(),
            worldpayAccountToken: $credentials->validation()->accountToken(),
            worldpayAcceptorId: (int)$credentials->validation()->merchantNumber(),
            worldpayApplicationId: (int)$credentials->validation()->accountId(),
            worldpayApplicationName: config(key: 'worldpay.application_name'),
            worldpayTerminalId: $credentials->validation()->terminalId(),
            isProduction: app()->isProduction(),
            logger: app()->make(abstract: LoggerInterface::class)
        );
    }

    /**
     * @param Client $guzzle
     *
     * @return Worldpay
     */
    public function setGuzzle(Client $guzzle): Worldpay
    {
        $this->guzzle = $guzzle;

        return $this;
    }

    /**
     * @param string $request
     *
     * @return Worldpay
     */
    public function setRequest(string $request): Worldpay
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTransactionResponseId(): string|null
    {
        return $this->transactionResponseId ?? null;
    }

    /**
     * @param string|null $transactionResponseId
     *
     * @return Worldpay
     */
    public function setTransactionResponseId(string|null $transactionResponseId): Worldpay
    {
        $this->transactionResponseId = $transactionResponseId;

        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionResponseCode(): string
    {
        return $this->transactionResponseCode ?? '';
    }

    /**
     * @param string $transactionResponseCode
     *
     * @return Worldpay
     */
    public function setTransactionResponseCode(string $transactionResponseCode): Worldpay
    {
        $this->transactionResponseCode = $transactionResponseCode;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getTransactionResponseData(): array|null
    {
        return $this->transactionResponseData;
    }

    /**
     * @param array|null $transactionResponseData
     *
     * @return void
     */
    public function setTransactionResponseData(array|null $transactionResponseData): void
    {
        $this->transactionResponseData = $transactionResponseData;
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function authCapture(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.worldpay.authorization_and_capture.start'));

        if ($this->isAchTransaction()) {
            return $this->authCaptureAch(inputData: $inputData);
        }

        return $this->authCaptureAuthRequest(inputData: $inputData, requestType: WorldpayRequestEnum::CREDIT_CARD_SALE);
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function authCaptureAch(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.operation.authorization_and_capture.ach'));

        return $this->achAuthCaptureAuthRequest(inputData: $inputData, requestType: WorldpayRequestEnum::CHECK_SALE);
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function authorize(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.worldpay.authorization.start'));

        if ($this->isAchTransaction()) {
            $this->authorizeAch();
            return false;
        }

        return $this->authCaptureAuthRequest(
            inputData: $inputData,
            requestType: WorldpayRequestEnum::CREDIT_CARD_AUTHORIZATION
        );
    }

    /**
     * @throws InvalidOperationException
     */
    protected function authorizeAch(): void
    {
        $this->setErrorMessage(errorMessage: __('messages.operation.authorization.ach_not_supported'));

        throw new InvalidOperationException(message: __('messages.operation.authorization.ach_not_supported'));
    }

    /**
     * @param array $inputData
     * @param WorldpayRequestEnum $requestType
     *
     * @throws \Exception
     */
    private function authCaptureAuthRequest(array $inputData, WorldpayRequestEnum $requestType): bool
    {
        $money = new MoneyHandler();
        $amount = new Money(amount: $inputData['amount'], currency: $inputData['currency']);

        $request = [
            'PaymentAccount' => [
                'PaymentAccountID' => $inputData['source']
            ],
            'Transaction' => [
                'TransactionAmount' => $money->formatFloat(money: $amount),
                'ReferenceNumber' => $inputData['reference_id'],
                'TicketNumber' => $inputData['reference_id'],
                'MarketCode' => WorldpayMarketCodeEnum::DEFAULT->value,
                'PartialApprovedFlag' => 0,
                // @codeCoverageIgnoreStart
                'DuplicateCheckDisableFlag' => !app()->isProduction(), // disable duplicate checking for all env expect for Production
                // @codeCoverageIgnoreEnd
            ],
            'Address' => [
                'BillingName' => $inputData['billing_name'],
                'BillingEmail' => $inputData['billing_details']['email'],
                'BillingAddress1' => $inputData['billing_details']['address']['line1'],
                'BillingAddress2' => $inputData['billing_details']['address']['line2'] ?? null,
                'BillingCity' => $inputData['billing_details']['address']['city'],
                'BillingState' => $inputData['billing_details']['address']['state'],
                'BillingZipcode' => $inputData['billing_details']['address']['postal_code']
            ]
        ];

        return $this->processAndHandleResponse(request: $request, requestType: $requestType);
    }

    /**
     * @param array $inputData
     * @param WorldpayRequestEnum $requestType
     *
     * @throws \Exception
     */
    protected function achAuthCaptureAuthRequest(array $inputData, WorldpayRequestEnum $requestType): bool
    {
        $this->logger->debug(message: __('messages.operation.capture.ach'));

        $money = new MoneyHandler();
        $amount = new Money(amount: $inputData['amount'], currency: $inputData['currency']);

        $request = [
            'Transaction' => [
                'TransactionAmount' => $money->formatFloat(money: $amount),
                'ReferenceNumber' => $inputData['reference_id'],
                'TicketNumber' => $inputData['reference_id'],
                'MarketCode' => WorldpayMarketCodeEnum::DEFAULT->value,
                'SECCode' => self::SEC_WEB,
                'PartialApprovedFlag' => 0
            ],
            'Address' => [
                'BillingName' => $inputData['billing_name'],
                'BillingEmail' => $inputData['billing_details']['email'],
                'BillingAddress1' => $inputData['billing_details']['address']['line1'],
                'BillingAddress2' => $inputData['billing_details']['address']['line2'] ?? null,
                'BillingCity' => $inputData['billing_details']['address']['city'],
                'BillingState' => $inputData['billing_details']['address']['state'],
                'BillingZipcode' => $inputData['billing_details']['address']['postal_code']
            ]
        ];

        $request += $this->buildAchRequestPaymentCredentials($inputData);

        return $this->processAndHandleResponse(request: $request, requestType: $requestType);
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function capture(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.worldpay.capture.start'));

        if ($this->isAchTransaction()) {
            return $this->captureAch();
        }

        return $this->captureCancelReturnRequest(
            inputData: $inputData,
            requestType: WorldpayRequestEnum::CREDIT_CARD_AUTHORIZATION_COMPLETION
        );
    }

    protected function captureAch(): bool
    {
        $this->setErrorMessage(errorMessage: __('messages.operation.capture.ach_not_supported'));

        throw new InvalidOperationException(message: __('messages.operation.capture.ach_not_supported'));
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function cancel(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.worldpay.cancel.start'));

        if ($this->isAchTransaction()) {
            return $this->cancelAch(inputData: $inputData);
        }

        return $this->captureCancelReturnRequest(
            inputData: $inputData,
            requestType: WorldpayRequestEnum::CREDIT_CARD_REVERSAL
        );
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     */
    protected function cancelAch(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.operation.cancel.ach'));

        return $this->captureCancelReturnRequest(inputData: $inputData, requestType: WorldpayRequestEnum::CHECK_VOID);
    }

    /**
     * @param array $inputData
     * @param WorldpayRequestEnum $requestType
     *
     * @throws \Exception
     */
    protected function captureCancelReturnRequest(array $inputData, WorldpayRequestEnum $requestType): bool
    {
        $money = new MoneyHandler();
        $amount = new Money(amount: $inputData['amount'], currency: $inputData['currency']);

        $request = [
            'Transaction' => [
                'TransactionID' => $inputData['transaction_id'],
                'TransactionAmount' => $money->formatFloat(money: $amount),
                'ReferenceNumber' => $inputData['reference_id'],
                'TicketNumber' => $inputData['reference_id'],
                'MarketCode' => WorldpayMarketCodeEnum::DEFAULT->value,
                'ReversalType' => WorldpayReversalTypeEnum::SYSTEM->value,
            ],
        ];

        return $this->processAndHandleResponse(request: $request, requestType: $requestType);
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     */
    public function credit(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.worldpay.return.start'));

        if ($this->isAchTransaction()) {
            return $this->creditAch(inputData: $inputData);
        }

        return $this->captureCancelReturnRequest(
            inputData: $inputData,
            requestType: WorldpayRequestEnum::CREDIT_CARD_RETURN
        );
    }

    /**
     * @param array $inputData
     *
     * @throws \Exception
     */
    protected function creditAch(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.operation.credit.ach'));

        return $this->captureCancelReturnRequest(inputData: $inputData, requestType: WorldpayRequestEnum::CHECK_RETURN);
    }

    /**
     * @param array $inputData
     *
     * @throws \JsonException
     *
     * @return bool
     */
    public function status(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.worldpay.status.start'));

        if ($this->isAchTransaction()) {
            return $this->statusAch(inputData: $inputData);
        }

        $request = [
            'Parameters' => [
                'TransactionID' => $inputData['transaction_id'],
                'ReferenceNumber' => $inputData['reference_id']
            ]
        ];

        return $this->processAndHandleResponse(
            request: $request,
            requestType: WorldpayRequestEnum::TRANSACTION_QUERY,
            withTerminal: false
        );
    }

    /**
     * @param array $inputData
     *
     * @throws \JsonException
     */
    protected function statusAch(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.operation.status.ach'));

        $request = [
            'Transaction' => [
                'TransactionId' => $inputData['transaction_id'],
                'ReferenceNumber' => $inputData['reference_id'],
                'MarketCode' => WorldpayMarketCodeEnum::DEFAULT->value
            ]
        ];

        return $this->processAndHandleResponse(request: $request, requestType: WorldpayRequestEnum::CHECK_QUERY);
    }

    /**
     * @param int|string|null $paymentAccountId
     * @param string|null $paymentAccountReferenceNumber
     *
     * @throws \JsonException
     *
     * @return object|null
     */
    public function getPaymentAccount(
        int|string|null $paymentAccountId = null,
        string|null $paymentAccountReferenceNumber = null
    ): object|null {
        $this->logger->debug(message: __('messages.worldpay.payment_account_query.start'));

        $request = [
            'PaymentAccountParameters' => [],
        ];

        if (!empty($paymentAccountId)) {
            $request['PaymentAccountParameters']['PaymentAccountID'] = $paymentAccountId;
        }

        if (!empty($paymentAccountReferenceNumber)) {
            $request['PaymentAccountParameters']['PaymentAccountReferenceNumber'] = $paymentAccountReferenceNumber;
        }

        if (empty($request['PaymentAccountParameters'])) {
            return null;
        }

        $isSuccess = $this->processAndHandleResponse(
            request: $request,
            requestType: WorldpayRequestEnum::PAYMENT_ACCOUNT_QUERY,
            withTerminal: false
        );

        return $isSuccess && !empty($this->getTransactionResponseData()['Items'])
            ? (object)array_pop($this->getTransactionResponseData()['Items'])
            : null;
    }

    /**
     * @inheritDoc
     *
     * @throws \JsonException
     */
    public function updatePaymentAccount(
        string $paymentAccountId,
        PaymentMethod $paymentMethod
    ): bool {
        $this->logger->debug(message: __('messages.worldpay.payment_account_update.start'));

        $this->setPaymentType(PaymentTypeEnum::from(value: $paymentMethod->payment_type_id));

        $request = [
            'PaymentAccount' => [
                'PaymentAccountID' => $paymentAccountId,
                'PaymentAccountType' => WorldpayPaymentAccountTypeEnum::ACH->value,
                'PaymentAccountReferenceNumber' => $paymentMethod->id,
            ],
            'Address' => [
                'BillingName' => $paymentMethod->name_on_account,
                'BillingEmail' => $paymentMethod->email,
                'BillingAddress1' => $paymentMethod->address_line1,
                'BillingAddress2' => $paymentMethod->address_line2,
                'BillingCity' => $paymentMethod->city,
                'BillingState' => $paymentMethod->province,
                'BillingZipcode' => $paymentMethod->postal_code,
            ]
        ];

        if (!$this->isAchTransaction()) {
            $request['PaymentAccount']['PaymentAccountType'] = WorldpayPaymentAccountTypeEnum::CreditCard->value;
            $request['Card'] = [
                'ExpirationMonth' => sprintf('%02d', $paymentMethod->cc_expiration_month),
                'ExpirationYear' => sprintf('%02d', (string)($paymentMethod->cc_expiration_year % 100)),
            ];
        }

        return $this->processAndHandleResponse(
            request: $request,
            requestType: WorldpayRequestEnum::PAYMENT_ACCOUNT_UPDATE,
            withTerminal: false
        );
    }

    /**
     * @inheritDoc
     */
    public function createTransactionSetup(
        int|string $referenceId,
        string $callbackUrl,
        string|null $name = null,
        string|null $address1 = null,
        string|null $address2 = null,
        string|null $city = null,
        string|null $province = null,
        string|null $postalCode = null,
        string|null $email = null,
        string|null $phone = null
    ): array|null {
        $this->logger->debug(message: __('messages.worldpay.create_transaction_setup.start'));

        $request = [
            'PaymentAccount' => [
                'PaymentAccountID' => '',
                'PaymentAccountType' => WorldpayPaymentAccountTypeEnum::CreditCard->value,
                'PaymentAccountReferenceNumber' => $referenceId,
            ],
            'TransactionSetup' => [
                'TransactionSetupID' => '',
                'TransactionSetupMethod' => WorldpayTransactionMethodEnum::PAYMENT_ACCOUNT_CREATE->value,
                'DeviceInputCode' => 0,
                'Device' => 0,
                'Embedded' => 1,
                'CVVRequired' => 1,
                'CompanyName' => config('worldpay.application_name'),
                'LogoURL' => '',
                'Tagline' => '',
                'AutoReturn' => 1,
                'WelcomeMessage' => '',
                'ProcessTransactionTitle' => 'Submit',
                'ReturnURL' => $callbackUrl,
                'CustomCss' => 'body {background-color: #f3f4f6;}.tableStandard {border: none !important;}#tdCardInformation {border: none !important;font-size: 30px;padding: 25px;background-color: #f3f4f6;padding-left: 0;}#divRequiredLegend {font-size: 13px;margin-top: 18px;}#tableCardInformation {border: none !important;}#tableManualEntry {width: 100%;font-size: 13px;}#trManualEntryCardNumber td {padding-bottom: 8px;}.tdField {padding-left: 10px;}#cardNumber {width: 240px;border-radius: 6px;padding: 8px;border: 1px solid rgb(209, 213, 219);box-shadow: rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0.05) 0px 1px 2px 0px;letter-spacing: 1px;}.selectOption {width: 80px;border-radius: 6px;padding: 8px;border: 1px solid rgb(209, 213, 219);box-shadow: rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0.05) 0px 1px 2px 0px }#tdManualEntry {padding: 18px;padding-top: 30px;padding-bottom: 30px;background-color: #FFF;border: none !important;border-radius: 10px;box-shadow: rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0.1) 0px 1px 3px 0px, rgba(0, 0, 0, 0.1) 0px 1px 2px -1px;}.tdTransactionButtons {padding-top: 20px;border: none;}.buttonEmbedded {background-color: #344c38 !important;padding-left: 24px !important;padding-right: 24px !important;padding-top: 12px !important;padding-bottom: 12px !important;text-transform: capitalize !important;border-radius: 5px;}.buttonCancel {background-color: none !important;border: 2px solid #344c38;font-size: 15px;color: #344c38 !important;text-decoration: none;font-weight: bold;padding-left: 24px !important;padding-right: 24px !important;padding-top: 12px !important;padding-bottom: 12px !important;text-transform: capitalize !important;border-radius: 5px;}',
                'ReturnURLTitle' => '',
                'OrderDetails' => '',
            ],
            'Address' => [
                'BillingName' => $name,
                'BillingAddress1' => $address1,
                'BillingAddress2' => $address2,
                'BillingCity' => $city,
                'BillingState' => $province,
                'BillingZipcode' => $postalCode,
                'BillingEmail' => $email,
                'BillingPhone' => $phone,
            ],

        ];

        $this->processAndHandleResponse(
            request: $request,
            requestType: WorldpayRequestEnum::TRANSACTION_SETUP,
            withTerminal: true
        );

        $response = $this->getParsedResponse();

        return $response['Response']['TransactionSetup'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function generateTransactionSetupUrl(int|string $transactionSetupId): string
    {
        $host = WorldpayRequestEnum::TRANSACTION_HOSTED_PAYMENTS->endpoint(isProduction: app()->isProduction());

        return "$host?TransactionSetupID=$transactionSetupId";
    }

    /**
     * @return string|null
     */
    public function getTransactionId(): string|null
    {
        return $this->getTransactionResponseId();
    }

    /**
     * @return string
     */
    public function getTransactionStatus(): string
    {
        return $this->getTransactionResponseCode();
    }

    /**
     * @return DeclineReasonEnum|null
     */
    public function getDeclineReason(): DeclineReasonEnum|null
    {
        if ($this->isSuccessful()) {
            return null;
        }

        try {
            return match (WorldpayResponseCodeEnum::tryFrom((int)$this->getTransactionStatus())) {
                WorldpayResponseCodeEnum::DECLINED,
                WorldpayResponseCodeEnum::AUTHORIZATION_FAILED,
                WorldpayResponseCodeEnum::NOT_AUTHORIZED => DeclineReasonEnum::DECLINED,
                WorldpayResponseCodeEnum::EXPIRED_CARD => DeclineReasonEnum::EXPIRED,
                WorldpayResponseCodeEnum::DUPLICATE => DeclineReasonEnum::DUPLICATE,
                WorldpayResponseCodeEnum::NON_FINANCIAL_CARD,
                WorldpayResponseCodeEnum::NOT_DEFINED,
                WorldpayResponseCodeEnum::INVALID_DATA,
                WorldpayResponseCodeEnum::INVALID_ACCOUNT,
                WorldpayResponseCodeEnum::INVALID_REQUEST => DeclineReasonEnum::INVALID,
                WorldpayResponseCodeEnum::PICK_UP_CARD => DeclineReasonEnum::FRAUD,
                WorldpayResponseCodeEnum::BALANCE_NOT_AVAILABLE,
                WorldpayResponseCodeEnum::OUT_OF_BALANCE => DeclineReasonEnum::INSUFFICIENT_FUNDS,
                WorldpayResponseCodeEnum::COMMUNICATION_ERROR,
                WorldpayResponseCodeEnum::HOST_ERROR,
                WorldpayResponseCodeEnum::ERROR => DeclineReasonEnum::ERROR,
                WorldpayResponseCodeEnum::REFERRAL_CALL_ISSUER => DeclineReasonEnum::CONTACT_FINANCIAL_INSTITUTION,
                default => throw new GatewayDeclineReasonUnmapped($this->getTransactionStatus()),
            };
        } catch (GatewayDeclineReasonUnmapped $exception) {
            $this->logger->warning(message: __('messages.gateway.found_unmapped_decline_reason'), context: [
                'gateway' => PaymentGatewayEnum::WORLDPAY,
                'response_code' => $this->getTransactionStatus(),
                'error' => $exception->getMessage()
            ]);

            return DeclineReasonEnum::DECLINED;
        }
    }

    private function isAchTransaction(): bool
    {
        return $this->getPaymentType() === PaymentTypeEnum::ACH;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->getTransactionResponseCode() === '0';
    }

    /**
     * @return array
     */
    public function getParsedResponse(): array
    {
        return $this->parsedResponse ?? [];
    }

    /**
     * @param array $parsedResponse
     *
     * @return Worldpay
     */
    public function setParsedResponse(array $parsedResponse): Worldpay
    {
        $this->parsedResponse = $parsedResponse;

        return $this;
    }

    private function buildHeaders(bool $withTerminal): array
    {
        $headers = [
            'Application' => [
                'ApplicationID' => $this->worldpayApplicationId,
                'ApplicationName' => $this->worldpayApplicationName,
                'ApplicationVersion' => self::APPLICATION_VERSION
            ],
            'Credentials' => [
                'AccountID' => $this->worldpayAccountId,
                'AccountToken' => $this->worldpayAccountToken,
                'AcceptorID' => $this->worldpayAcceptorId,
            ],
        ];

        if ($withTerminal) {
            $headers['Terminal'] = [
                'TerminalID' => $this->worldpayTerminalId,
                'LaneNumber' => self::LANE_NUMBER,
                'TerminalCapabilityCode' => 0,
                'MotoECICode' => 0,
                'TerminalEnvironmentCode' => 0,
                'CardPresentCode' => self::CARD_PRESENT_NOT_PRESENT,
                'CVVPresenceCode' => 0,
                'CardInputCode' => 0,
                'CardholderPresentCode' => 0
            ];
        }

        return $headers;
    }

    /**
     * @param array $request
     * @param WorldpayRequestEnum $action
     * @param bool $withTerminal
     *
     * @throws \JsonException
     * @throws \Exception
     */
    private function process(array $request, WorldpayRequestEnum $action, bool $withTerminal): bool
    {
        $request = array_merge($this->buildHeaders(withTerminal: $withTerminal), $request);

        $xml = $this->arrayToXml(
            array: $request,
            rootElement: sprintf('<%s xmlns="%s"/>', $action->value, $action->targetNamespace())
        );

        $this->setRequest(request: $xml->asXML());

        $this->logger->debug(message: __('messages.worldpay.send_request'), context: [
            'body' => $xml->asXML(),
            'endpoint' => $action->endpoint(isProduction: $this->isProduction),
        ]);

        try {
            $httpResponse = $this->guzzle->post(
                uri: $action->endpoint(isProduction: $this->isProduction),
                options: [
                    'timeout' => 30,
                    'connect_timeout' => 3,
                    'headers' => [
                        'Content-type' => 'text/xml'
                    ],
                    'body' => $xml->asXML()
                ]
            );
        } catch (ConnectException $e) {
            $this->logger->error(message: __('messages.error.networking', ['message' => $e->getMessage()]));
            $this->setErrorMessage(errorMessage: $e->getMessage());

            return false;
        } catch (ClientException $e) {
            $this->logger->error(message: __('messages.error.client', ['message' => $e->getMessage()]));
            $this->setErrorMessage(errorMessage: $e->getMessage());

            return false;
        } catch (ServerException $e) {
            $this->logger->error(message: __('messages.error.server', ['message' => $e->getMessage()]));
            $this->setErrorMessage(errorMessage: $e->getMessage());

            return false;
        } catch (GuzzleException $e) {
            $this->logger->error(message: __('messages.error.connection', ['message' => $e->getMessage()]));
            $this->setErrorMessage(errorMessage: $e->getMessage());

            return false;
        }

        $statusCode = $httpResponse->getStatusCode();
        $this->logger->debug(message: __('messages.response.http_code', ['code' => $statusCode]));
        $response = $httpResponse->getBody()->getContents();

        $this->setHttpCode(httpCode: $statusCode);
        $this->addResponse(response: $response);

        $this->setParsedResponse(
            parsedResponse: JsonDecoder::decode(JsonDecoder::encode(simplexml_load_string($response)))
        );

        return true;
    }

    /**
     * @param array $request
     * @param WorldpayRequestEnum $requestType
     * @param mixed $withTerminal
     *
     * @throws \JsonException
     */
    private function processAndHandleResponse(
        array $request,
        WorldpayRequestEnum $requestType,
        $withTerminal = true
    ): bool {
        $result = $this->process(request: $request, action: $requestType, withTerminal: $withTerminal);

        if (!$result) {
            $this->logger->debug(message: __('messages.response.abort'));

            return false;
        }

        $response = $this->getParsedResponse();

        $this->logger->debug(message: 'Response: ' . print_r(value: $response, return: true));
        $expressResponseCode = $response['Response']['ExpressResponseCode'];
        $transactionId = $response['Response']['Transaction']['TransactionID'] ?? $response['Response']['Transaction']['ReferenceNumber'] ?? null;
        $responseData = $response['Response']['QueryData'] ?? null;

        $this->setTransactionResponseCode(transactionResponseCode: $expressResponseCode)
            ->setTransactionResponseId(transactionResponseId: $transactionId)
            ->setTransactionResponseData(transactionResponseData: $responseData);

        // Update correct payment status based on the transaction status code
        $transactionStatusCode = (int) data_get($response, 'Response.ReportingData.Items.Item.TransactionStatusCode', 0);
        $paymentStatus = $this->getPaymentStatusFromTransactionStatusCode(transactionStatusCode: $transactionStatusCode);
        if (!is_null($paymentStatus)) {
            $this->setPaymentStatus(paymentStatus: $paymentStatus);
        }

        if (!$this->isSuccessful()) {
            $this->setErrorMessage($response['Response']['ExpressResponseMessage'] ?? null);
        }

        $this->logger->debug(message: __('messages.worldpay.completed', ['method' => $requestType->value]));

        return true;
    }

    private function getPaymentStatusFromTransactionStatusCode(int $transactionStatusCode): PaymentStatusEnum|null
    {
        return match ($transactionStatusCode) {
            WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_RETURNED->value => PaymentStatusEnum::RETURNED,
            WorldpayResponseCodeEnum::ERROR->value => PaymentStatusEnum::RETURNED,
            WorldpayResponseCodeEnum::TRANSACTION_STATUS_CODE_SETTLED->value => PaymentStatusEnum::SETTLED,
            default => null,
        };
    }

    private function buildAchRequestPaymentCredentials(array $inputData): array
    {
        if (isset($inputData['source'], $inputData['ach_account_type'])) {
            return [
                'PaymentAccount' => [
                    'PaymentAccountID' => $inputData['source'],
                ],
                'DemandDepositAccount' => [
                    'DDAAccountType' => $inputData['ach_account_type']->ddaAccountTypeId(),
                ],
            ];
        }

        if (isset($inputData['ach_account_number'], $inputData['ach_routing_number'], $inputData['ach_account_type'])) {
            return [
                'DemandDepositAccount' => [
                    'AccountNumber' => SodiumEncryptHelper::decrypt($inputData['ach_account_number']),
                    'RoutingNumber' => $inputData['ach_routing_number'],
                    'DDAAccountType' => $inputData['ach_account_type']->ddaAccountTypeId(),
                    'CheckType' => $inputData['ach_account_type']->checkType()
                ],
            ];
        }

        throw new OperationValidationException(errors: [__('messages.payment_method.validate.incorrect_ach')]);
    }
}
