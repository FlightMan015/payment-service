<?php

declare(strict_types=1);

namespace App\Helpers;

use SlackPhp\BlockKit\Kit;

class SlackMessageBuilder
{
    private array $blocks = [];

    /**
     * @return self
     */
    public static function instance(): self
    {
        return new self();
    }

    /**
     * @param string $text
     *
     * @return static
     */
    public function header(string $text): static
    {
        $this->blocks[] = Kit::header(text: $text);

        return $this;
    }

    /**
     * @param array $items
     *
     * @return static
     */
    public function context(array $items): static
    {
        $this->blocks[] = Kit::context(elements: $items);

        return $this;
    }

    /**
     * @param string $environment
     * @param string|null $notify
     *
     * @return static
     */
    public function messageContext(string $environment, string|null $notify = null): static
    {
        $items = [
            'environment' => Kit::mrkdwnText(text: "Environment: *$environment*"),
        ];

        if (!is_null($notify)) {
            $items['notify'] = Kit::mrkdwnText(text: "Notify: <$notify>");
        }

        return $this->context(items: $items);
    }

    /**
     * @param string $text
     *
     * @return static
     */
    public function section(string $text): static
    {
        $this->blocks[] = Kit::section(text: Kit::mrkdwnText(text: $text));

        return $this;
    }

    /**
     * @param array $items
     *
     * @return static
     */
    public function keyValueList(array $items): static
    {
        $textValue = '';

        foreach ($items as $key => $value) {
            $textValue .= "- $key: *$value*\n";
        }

        return $this->section(text: $textValue);
    }

    /**
     * @param string $text
     *
     * @return static
     */
    public function codeBlock(string $text): static
    {
        $this->blocks[] = Kit::section(text: Kit::mrkdwnText(text: "```$text```"));

        return $this;
    }

    /**
     * @return array blocks to send
     */
    public function build(): array
    {
        return $this->blocks;
    }
}
