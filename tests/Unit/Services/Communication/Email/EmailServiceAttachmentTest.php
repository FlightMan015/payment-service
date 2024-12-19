<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Services\Communication\Email;

use App\Services\Communication\Email\EmailServiceAttachment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class EmailServiceAttachmentTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('attachmentDataProvider')]
    public function it_returns_valid_array_for_attachment(EmailServiceAttachment $attachment, array $expectedArray): void
    {
        $this->assertEquals($expectedArray, $attachment->toArray());
    }

    public static function attachmentDataProvider(): iterable
    {
        yield 'attachment without content-id' => [
            'attachment' => new EmailServiceAttachment(
                content: base64_encode('test content'),
                fileName: 'test.txt',
                type: 'text/plain',
                disposition: 'attachment'
            ),
            'expectedArray' => [
                'content' => base64_encode('test content'),
                'filename' => 'test.txt',
                'type' => 'text/plain',
                'disposition' => 'attachment'
            ],
        ];

        yield 'inline attachment with content-id' => [
            'attachment' => new EmailServiceAttachment(
                content: base64_encode('test content'),
                fileName: 'test.txt',
                type: 'text/plain',
                disposition: 'inline',
                contentId: 'test-content-id'
            ),
            'expectedArray' => [
                'content' => base64_encode('test content'),
                'filename' => 'test.txt',
                'type' => 'text/plain',
                'disposition' => 'inline',
                'content-id' => 'test-content-id'
            ],
        ];
    }
}
