<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Services\Communication\Email;

use App\Services\Communication\Email\EmailServiceAttachment;
use App\Services\Communication\Email\MarketingEmailService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\Communication\MarketingService\MarketingEmailServiceResponseStub;
use Tests\Unit\UnitTestCase;

class MarketingEmailServiceTest extends UnitTestCase
{
    private MarketingEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpService();
    }

    #[Test]
    #[DataProvider('sendEmailDataProvider')]
    public function it_properly_returns_a_response_while_sending_email(
        string $to,
        string $from,
        array|null $cc,
        array|null $bcc
    ): void {
        Http::fake(['*' => Http::response(MarketingEmailServiceResponseStub::sendSuccessResponse())]);

        $this->service->setToEmail($to);
        $this->assertEquals($to, $this->service->getToEmail());
        $this->service->setFromEmail($from);
        $this->assertEquals($from, $this->service->getFromEmail());

        if ($cc !== null) {
            $this->service->setCcEmails($cc);
            $this->assertEquals($cc, $this->service->getCcEmails());
        }

        if ($bcc !== null) {
            $this->service->setBccEmails($bcc);
            $this->assertEquals($bcc, $this->service->getBccEmails());
        }

        $templateName = 'templateName';
        $templateData = [
            'name' => 'John Doe'
        ];
        $attachments = [
            new EmailServiceAttachment(
                content: base64_encode('test content'),
                fileName: 'test.txt',
                type: 'text/plain',
                disposition: 'attachment'
            )
        ];

        $result = $this->service->send($templateName, $templateData, $attachments);

        $this->assertEquals(202, $result['status']);
    }

    #[Test]
    public function it_throws_exception_if_trying_to_set_null_as_toEmail(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The email address to send the email TO is required');

        $this->service->setToEmail(null);

    }

    public static function sendEmailDataProvider(): iterable
    {
        yield 'with cc and bcc' => ['to@email.com', 'from@email.com', ['cc@email.com'], ['bcc@email.com']];
        yield 'cc and bcc empty' => ['to@email.com', 'from@email.com', [], []];
        yield 'cc and bcc null' => ['to@email.com', 'from@email.com', null, null];
    }

    private function setUpService(): void
    {
        $this->service = new MarketingEmailService();
        $this->service->setFromEmail('from@goaptive.com');
        $this->service->setToEmail('to@goaptive.com');
    }
}
