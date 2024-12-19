<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Gateways;

use App\Api\DTO\GatewayInitializationDTO;
use App\Helpers\JsonDecoder;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Enums\WorldpayMarketCodeEnum;
use App\PaymentProcessor\Enums\WorldpayRequestEnum;
use App\PaymentProcessor\Enums\WorldpayResponseCodeEnum;
use App\PaymentProcessor\Enums\WorldpayReversalTypeEnum;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\GatewayDeclineReasonUnmapped;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\Traits\ConvertsArrayToXml;
use Aptive\Component\Money\MoneyHandler;
use Aptive\Worldpay\CredentialsRepository\Credentials\Credentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Str;
use Money\Money;
use Psr\Log\LoggerInterface;

class WorldpayTokenexTransparent extends AbstractGateway implements GatewayInterface
{
    use ConvertsArrayToXml;

    private const string LANE_NUMBER = '01';
    private const string CARD_PRESENT_NOT_PRESENT = '3';
    private const string APPLICATION_VERSION = '0';

    private array $parsedResponse;
    private string|null $transactionResponseId;
    private string $transactionResponseCode;
    private array|null $transactionResponseData = null;

    private function __construct(
        private Client $guzzle,
        private readonly string $tokenexUrl,
        private readonly string $tokenexApiKey,
        private readonly string $tokenexId,
        private readonly GatewayInitializationDTO $gatewayInitializationDTO,
        private readonly Credentials $worldpayCredentials,
        private readonly bool $isProduction,
        protected LoggerInterface|null $logger,
    ) {
        $this->setLogger(logger: $this->logger);
    }

    /**
     * @param GatewayInitializationDTO $gatewayInitializationDTO
     * @param Credentials $credentials
     *
     * @throws BindingResolutionException
     *
     * @return self
     */
    public static function make(
        GatewayInitializationDTO $gatewayInitializationDTO,
        Credentials $credentials
    ): self {
        return new self(
            guzzle: app()->make(abstract: Client::class),
            tokenexUrl: config(key: 'tokenex.url'),
            tokenexApiKey: config(key: 'tokenex.service_client_secret'),
            tokenexId: (string)config(key: 'tokenex.service_token_id'),
            gatewayInitializationDTO: $gatewayInitializationDTO,
            worldpayCredentials: $credentials,
            isProduction: app()->isProduction(),
            logger: app()->make(abstract: LoggerInterface::class)
        );
    }

    /**
     * @param Client $guzzle
     *
     * @return self
     */
    public function setGuzzle(Client $guzzle): self
    {
        $this->guzzle = $guzzle;

        return $this;
    }

    /**
     * @param string $request
     *
     * @return self
     */
    public function setRequest(string $request): self
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
     * @return self
     */
    public function setTransactionResponseId(string|null $transactionResponseId): self
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
     * @return self
     */
    public function setTransactionResponseCode(string $transactionResponseCode): self
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
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.auth_capture.start'));

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
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.auth_capture.ach'));

        throw new InvalidOperationException(message: __('messages.worldpay_tokenex_transparent.ach_not_supported'));
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
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.authorization.start'));

        if ($this->isAchTransaction()) {
            return $this->authorizeAch();
        }

        return $this->authCaptureAuthRequest(
            inputData: $inputData,
            requestType: WorldpayRequestEnum::CREDIT_CARD_AUTHORIZATION
        );
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
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.capture.start'));

        if ($this->isAchTransaction()) {
            return $this->captureAch();
        }

        return $this->captureCancelReturnRequest(
            inputData: $inputData,
            requestType: WorldpayRequestEnum::CREDIT_CARD_AUTHORIZATION_COMPLETION
        );
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
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.cancel.start'));

        if ($this->isAchTransaction()) {
            return $this->cancelAch();
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
    public function credit(array $inputData): bool
    {
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.credit.start'));

        if ($this->isAchTransaction()) {
            return $this->creditAch();
        }

        return $this->captureCancelReturnRequest(
            inputData: $inputData,
            requestType: WorldpayRequestEnum::CREDIT_CARD_RETURN
        );
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
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.status.start'));

        if ($this->isAchTransaction()) {
            return $this->statusAch();
        }

        $this->validateCreditCardData();

        $request = [
            'Card' => [
                'CardNumber' => sprintf('{{{%s}}}', $this->gatewayInitializationDTO->creditCardToken),
                'ExpirationMonth' => sprintf('%02d', $this->gatewayInitializationDTO->creditCardExpirationMonth),
                'ExpirationYear' => sprintf('%02d', (string)($this->gatewayInitializationDTO->creditCardExpirationYear % 100)),
            ],
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
     * @param int|string|null $paymentAccountId
     * @param string|null $paymentAccountReferenceNumber
     *
     * @return object|null
     */
    public function getPaymentAccount(
        int|string|null $paymentAccountId = null,
        string|null $paymentAccountReferenceNumber = null
    ): object|null {
        throw new InvalidOperationException(message: __('messages.worldpay_tokenex_transparent.get_payment_account.not_supported'));
    }

    /**
     * @inheritDoc
     */
    public function updatePaymentAccount(
        string $paymentAccountId,
        PaymentMethod $paymentMethod
    ): bool {
        return false;
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     *
     * @deprecated
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
        return null;
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     *
     * @deprecated
     */
    public function generateTransactionSetupUrl(int|string $transactionSetupId): string|null
    {
        return null;
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
                'gateway' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT,
                'response_code' => $this->getTransactionStatus(),
                'error' => $exception->getMessage()
            ]);

            return DeclineReasonEnum::DECLINED;
        }
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
     * @return self
     */
    public function setParsedResponse(array $parsedResponse): self
    {
        $this->parsedResponse = $parsedResponse;

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return bool
     */
    protected function authorizeAch(): bool
    {
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.authorization.ach'));

        throw new InvalidOperationException(message: __('messages.worldpay_tokenex_transparent.ach_not_supported'));
    }

    protected function captureAch(): bool
    {
        $this->setErrorMessage(errorMessage: __('messages.operation.capture.not_supported_ach'));

        throw new InvalidOperationException(message: __('messages.worldpay_tokenex_transparent.ach_not_supported'));
    }

    /**
     * @throws \Exception
     */
    protected function cancelAch(): bool
    {
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.cancel.ach'));

        throw new InvalidOperationException(message: __('messages.worldpay_tokenex_transparent.ach_not_supported'));
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

        $this->validateCreditCardData();

        $request = [
            'Card' => [
                'CardNumber' => sprintf('{{{%s}}}', $this->gatewayInitializationDTO->creditCardToken),
                'ExpirationMonth' => sprintf('%02d', $this->gatewayInitializationDTO->creditCardExpirationMonth),
                'ExpirationYear' => sprintf('%02d', (string)($this->gatewayInitializationDTO->creditCardExpirationYear % 100)),
            ],
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

    protected function creditAch(): bool
    {
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.credit.ach'));

        throw new InvalidOperationException(message: __('messages.worldpay_tokenex_transparent.ach_not_supported'));
    }

    protected function statusAch(): bool
    {
        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.status.ach'));

        throw new InvalidOperationException(message: __('messages.worldpay_tokenex_transparent.ach_not_supported'));
    }

    private function isAchTransaction(): bool
    {
        return $this->getPaymentType() === PaymentTypeEnum::ACH;
    }

    /**
     * @param array $inputData
     * @param WorldpayRequestEnum $requestType
     *
     * @throws \JsonException
     * @throws \Exception
     */
    private function authCaptureAuthRequest(array $inputData, WorldpayRequestEnum $requestType): bool
    {
        $money = new MoneyHandler();
        $amount = new Money(amount: $inputData['amount'], currency: $inputData['currency']);

        $this->validateCreditCardData();

        $request = [
            'Card' => [
                'CardNumber' => sprintf('{{{%s}}}', $this->gatewayInitializationDTO->creditCardToken),
                'ExpirationMonth' => sprintf('%02d', $this->gatewayInitializationDTO->creditCardExpirationMonth),
                'ExpirationYear' => sprintf('%02d', (string)($this->gatewayInitializationDTO->creditCardExpirationYear % 100)),
            ],
            'Transaction' => [
                'TransactionAmount' => $money->formatFloat(money: $amount),
                'ReferenceNumber' => $inputData['reference_id'],
                'TicketNumber' => $inputData['reference_id'],
                'MarketCode' => WorldpayMarketCodeEnum::DEFAULT->value,
                'PartialApprovedFlag' => 0,
                // @codeCoverageIgnoreStart
                'DuplicateCheckDisableFlag' => !app()->isProduction(), // disable duplicate checking for all envs expect for Production
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

        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.send_request'), context: [
            'body' => $xml->asXML(),
            'endpoint' => $action->endpoint(isProduction: $this->isProduction),
        ]);

        try {
            $httpResponse = $this->guzzle->post(
                uri: $this->tokenexUrl,
                options: [
                    'timeout' => 30,
                    'connect_timeout' => 3,
                    'headers' => [
                        'Content-type' => 'text/xml',
                        'tx-tokenex-id' => $this->tokenexId,
                        'tx-apikey' => $this->tokenexApiKey,
                        'tx-url' => $action->endpoint(isProduction: $this->isProduction),
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
        $response = (string) $httpResponse->getBody();

        $this->setHttpCode(httpCode: $statusCode);
        $this->addResponse(response: $response);

        $this->setParsedResponse(
            parsedResponse: $this->parsedResponseFromJsonOrXml(response: $response)
        );

        return true;
    }

    private function buildHeaders(bool $withTerminal): array
    {
        $headers = [
            'Application' => [
                'ApplicationID' => (int) $this->worldpayCredentials->validation()->accountId(),
                'ApplicationName' => config(key: 'worldpay.application_name'),
                'ApplicationVersion' => self::APPLICATION_VERSION
            ],
            'Credentials' => [
                'AccountID' => (int) $this->worldpayCredentials->validation()->accountId(),
                'AccountToken' => $this->worldpayCredentials->validation()->accountToken(),
                'AcceptorID' => (int) $this->worldpayCredentials->validation()->merchantNumber(),
            ],
        ];

        if ($withTerminal) {
            $headers['Terminal'] = [
                'TerminalID' => $this->worldpayCredentials->validation()->terminalId(),
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

        $this->setTransactionResponseCode(transactionResponseCode: $expressResponseCode)
            ->setTransactionResponseId(transactionResponseId: $transactionId)
            ->setTransactionResponseData(transactionResponseData: $response['Response']['QueryData'] ?? null);

        if (!$this->isSuccessful()) {
            $this->setErrorMessage($response['Response']['ExpressResponseMessage'] ?? null);
        }

        $this->logger->debug(message: __('messages.worldpay_tokenex_transparent.completed', ['method' => $requestType->value]));

        return true;
    }

    /**
     * @param string $response
     *
     * @throws \JsonException
     */
    private function parsedResponseFromJsonOrXml(string $response): array
    {
        // Worldpay's response is in XML format, if it is in JSON, it should be a failed response from Tokenex
        if (Str::isJson($response)) {
            $arr = JsonDecoder::decode($response);

            return [
                'Response' => [
                    'ExpressResponseCode' => '103',
                    'ExpressResponseMessage' => __('messages.worldpay_tokenex_transparent.error', ['message' => $arr['error']]),
                    'Transaction' => [
                        'ReferenceNumber' => $arr['referenceNumber'] ?? null
                    ],
                ],
            ];
        }

        return JsonDecoder::decode(JsonDecoder::encode(simplexml_load_string($response)));
    }

    private function validateCreditCardData(): void
    {
        if (is_null($this->gatewayInitializationDTO->creditCardExpirationMonth) || is_null($this->gatewayInitializationDTO->creditCardExpirationYear)) {
            throw new CreditCardValidationException(message: __('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required'));
        }
    }
}
