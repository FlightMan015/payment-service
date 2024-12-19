<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\SlackMessageBuilder;
use PHPUnit\Framework\Attributes\Test;
use SlackPhp\BlockKit\Kit;
use Tests\Unit\UnitTestCase;

class SlackMessageBuilderTest extends UnitTestCase
{
    #[Test]
    public function header_method_builds_header_as_expected(): void
    {
        $builder = SlackMessageBuilder::instance();
        $headerText = 'Test Header';
        $builder->header($headerText);
        $blocks = $builder->build();

        $this->assertCount(1, $blocks);
        $this->assertEquals(Kit::header(text: $headerText), $blocks[0]);
    }

    #[Test]
    public function context_method_builds_context_as_expected(): void
    {
        $builder = SlackMessageBuilder::instance();
        $items = [
            Kit::plainText(text: 'Item 1'),
            Kit::plainText(text: 'Item 2'),
            Kit::plainText(text: 'Item 3'),
        ];
        $builder->context($items);
        $blocks = $builder->build();

        $this->assertCount(1, $blocks);
        $this->assertEquals(Kit::context(elements: $items), $blocks[0]);
    }

    #[Test]
    public function message_context_method_with_notify_builds_context_as_expected(): void
    {
        $builder = SlackMessageBuilder::instance();
        $environment = 'Production';
        $notify = '@someone';
        $builder->messageContext($environment, $notify);
        $blocks = $builder->build();

        $this->assertCount(1, $blocks);
        $expectedItems = [
            'environment' => Kit::mrkdwnText(text: "Environment: *$environment*"),
            'notify' => Kit::mrkdwnText(text: "Notify: <$notify>"),
        ];
        $this->assertEquals(Kit::context(elements: $expectedItems), $blocks[0]);
    }

    #[Test]
    public function message_context_method_without_notify_builds_context_as_expected(): void
    {
        $builder = SlackMessageBuilder::instance();
        $environment = 'Development';
        $builder->messageContext($environment);
        $blocks = $builder->build();

        $this->assertCount(1, $blocks);
        $expectedItems = [
            'environment' => Kit::mrkdwnText(text: "Environment: *$environment*"),
        ];
        $this->assertEquals(Kit::context(elements: $expectedItems), $blocks[0]);
    }

    #[Test]
    public function section_method_builds_section_as_expected(): void
    {
        $builder = SlackMessageBuilder::instance();
        $sectionText = 'This is a section';
        $builder->section($sectionText);
        $blocks = $builder->build();

        $this->assertCount(1, $blocks);
        $expectedSection = Kit::section(text: Kit::mrkdwnText(text: $sectionText));
        $this->assertEquals($expectedSection, $blocks[0]);
    }

    #[Test]
    public function key_value_list_method_builds_section_as_expected(): void
    {
        $builder = SlackMessageBuilder::instance();
        $items = [
            'Item 1' => 'Value 1',
            'Item 2' => 'Value 2',
            'Item 3' => 'Value 3',
        ];
        $builder->keyValueList($items);
        $blocks = $builder->build();

        $this->assertCount(1, $blocks);
        $expectedSectionText = "- Item 1: *Value 1*\n- Item 2: *Value 2*\n- Item 3: *Value 3*\n";
        $expectedSection = Kit::section(text: Kit::mrkdwnText(text: $expectedSectionText));
        $this->assertEquals($expectedSection, $blocks[0]);
    }

    #[Test]
    public function code_block_method_builds_section_as_expected(): void
    {
        $builder = SlackMessageBuilder::instance();
        $code = 'echo "Hello, World!"';
        $builder->codeBlock($code);
        $blocks = $builder->build();

        $this->assertCount(1, $blocks);
        $expectedSection = Kit::section(text: Kit::mrkdwnText(text: "```$code```"));
        $this->assertEquals($expectedSection, $blocks[0]);
    }
}
