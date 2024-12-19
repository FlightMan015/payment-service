<?php

declare(strict_types=1);

namespace App\Services\Communication\Email;

/**
 * Represents an attachment on an email
 */
class EmailServiceAttachment
{
    /**
     * @param string $content The contents of the attachment
     * @param string $fileName The file name for the attachment
     * @param string $type The content type for the attachment
     * @param string $disposition The disposition for the attachment
     * @param string|null $contentId The content-id for the attachment, if embedded
     */
    public function __construct(
        public string $content,
        public string $fileName,
        public string $type,
        public string $disposition,
        public string|null $contentId = null
    ) {
    }

    /**
     * Returns the values as an array
     */
    public function toArray(): array
    {
        return array_filter([
            'content' => $this->content,
            'filename' => $this->fileName,
            'type' => $this->type,
            'disposition' => $this->disposition,
            'content-id' => $this->contentId
        ]);
    }
}
