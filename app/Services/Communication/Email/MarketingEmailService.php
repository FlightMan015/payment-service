<?php

declare(strict_types=1);

namespace App\Services\Communication\Email;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketingEmailService implements EmailService
{
    public const int RETRIES = 3;
    public const int MILLISECONDS_TO_WAIT_BETWEEN_RETRIES = 500;

    private string $apiKey;
    private string $baseUrl;
    private string $endpoint;
    private string $endpointWithAttachments;

    private string $fromEmail;
    private string $toEmail;
    private array $ccEmails;
    private array $bccEmails;

    /**
     * @param array $config
     */
    public function __construct(public readonly array $config = [])
    {
        $this->apiKey = config('marketing-messaging-api.key', '');
        $this->baseUrl = config('marketing-messaging-api.url', '');
        $this->endpoint = $this->endpointWithAttachments = config('marketing-messaging-api.endpoint', '');
        $this->fromEmail = config('marketing-messaging-api.customer_support_email');
    }

    /**
     * Sends an email message via the Service
     *
     * @param string $templateName The email template to use for sending
     * @param array $templateData The data to use for the email template
     * @param EmailServiceAttachment[]|null $attachments
     *
     * @throws RequestException
     * @throws ConnectionException
     *
     * @return mixed The response from the API
     */
    public function send(
        string $templateName,
        array $templateData,
        array|null $attachments = null
    ): mixed {
        $endpoint = $this->endpoint;

        $data = [
            'type' => 'email',
            'emailTemplate' => $templateName,
            'templateData' => $templateData,
            'toEmail' => $this->toEmail,
            'fromEmail' => $this->fromEmail,
            'state' => null,
        ];

        if (isset($this->ccEmails)) {
            $data['ccEmail'] = $this->ccEmails;
        }

        if (isset($this->bccEmails)) {
            $data['bccEmail'] = $this->bccEmails;
        }

        if (!is_null($attachments)) {
            $data['attachments'] = array_map(callback: static fn ($attachment) => $attachment->toArray(), array: $attachments);
            $endpoint = $this->endpointWithAttachments;
        }

        return $this->post($endpoint, $data);
    }

    /**
     * Make a get request to an api endpoint
     *
     * @param string $endpoint - The specific endpoint to call
     * @param array $data - The data to include in the body of the request
     *
     * @throws RequestException
     * @throws ConnectionException
     *
     * @return mixed
     */
    protected function post(string $endpoint, array $data = []): mixed
    {
        Log::debug(message: 'Sending marketing email request', context: [
            'url' => $this->baseUrl . $endpoint,
            'token' => Str::mask(string: $this->apiKey, character: '*', index: 4, length: 10),
            'data' => $data
        ]);

        $response = Http::retry(self::RETRIES, self::MILLISECONDS_TO_WAIT_BETWEEN_RETRIES)
            ->withToken($this->apiKey)
            ->post($this->baseUrl . $endpoint, $data)
            ->throw() // Throw an Illuminate\Http\Client\RequestException if not successful
            ->json(); // Return the body of the response

        Log::debug('Sending marketing email response', $response);

        return $response;
    }

    /**
     * Set the from: email address to send the email From
     *
     * @param string $fromEmail
     *
     * @return self
     */
    public function setFromEmail(string $fromEmail): self
    {
        $this->fromEmail = trim($fromEmail);

        return $this;
    }

    /**
     * Get the email to send the email From
     *
     * @return string
     */
    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    /**
     * Set the email address to send the email TO
     *
     * @param string|null $toEmail
     *
     * @throws \RuntimeException
     *
     * @return self
     */
    public function setToEmail(string|null $toEmail): self
    {
        if (is_null($toEmail)) {
            throw new \RuntimeException('The email address to send the email TO is required');
        }

        $this->toEmail = trim($toEmail);

        return $this;
    }

    /**
     * Get the email address to send the email TO
     *
     * @return string
     */
    public function getToEmail(): string
    {
        return $this->toEmail;
    }

    /**
     * Set the email addresses to cc the email to
     *
     * @param array $ccEmails
     *
     * @return self
     */
    public function setCcEmails(array $ccEmails): self
    {
        $this->ccEmails = $ccEmails;

        return $this;
    }

    /**
     * Get the email addresses to cc the email to
     *
     * @return array
     */
    public function getCcEmails(): array
    {
        return $this->ccEmails;
    }

    /**
     * Set the email addresses to bcc the email to
     *
     * @param array $bccEmails
     *
     * @return self
     */
    public function setBccEmails(array $bccEmails): self
    {
        $this->bccEmails = $bccEmails;

        return $this;
    }

    /**
     * Get the email addresses to bcc the email to
     *
     * @return array
     */
    public function getBccEmails(): array
    {
        return $this->bccEmails;
    }
}
