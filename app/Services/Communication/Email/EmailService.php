<?php

declare(strict_types=1);

namespace App\Services\Communication\Email;

interface EmailService
{
    /**
     * Sends an email message via the Service
     *
     * @param string $templateName The email template to use for sending
     * @param array $templateData The data to use for the email template
     * @param EmailServiceAttachment[]|null $attachments
     *
     * @return mixed The response from the API
     */
    public function send(
        string $templateName,
        array $templateData,
        array|null $attachments = null
    ): mixed;

    /**
     * Set the from: email address to send the email From
     *
     * @param string $fromEmail
     *
     * @return self
     */
    public function setFromEmail(string $fromEmail): self;

    /**
     * Get the email to send the email From
     *
     * @return string|null
     */
    public function getFromEmail(): string|null;

    /**
     * Set the email address to send the email TO
     *
     * @param string|null $toEmail
     *
     * @return self
     */
    public function setToEmail(string|null $toEmail): self;

    /**
     * Get the email address to send the email TO
     *
     * @return string|null
     */
    public function getToEmail(): string|null;

    /**
     * Set the email addresses to cc the email to
     *
     * @param array $ccEmails
     *
     * @return self
     */
    public function setCcEmails(array $ccEmails): self;

    /**
     * Get the email addresses to cc the email to
     *
     * @return array|null
     */
    public function getCcEmails(): array|null;

    /**
     * Set the email addresses to bcc the email to
     *
     * @param array $bccEmails
     *
     * @return self
     */
    public function setBccEmails(array $bccEmails): self;

    /**
     * Get the email addresses to bcc the email to
     *
     * @return array|null
     */
    public function getBccEmails(): array|null;
}
