<?php

namespace Tests\Unit\Support\Markdown;

use App\Support\Markdown\Markdown;
use Tests\TestCase;

class MarkdownTest extends TestCase
{
    public function test_renders_headings(): void
    {
        $html = Markdown::toHtml("# Titel\n\n## Untertitel");

        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<h2>Untertitel</h2>', $html);
    }

    public function test_renders_links(): void
    {
        $html = Markdown::toHtml('See [Google](https://google.com) for more.');

        $this->assertStringContainsString('<a href="https://google.com">Google</a>', $html);
    }

    public function test_drops_unsafe_link_schemes(): void
    {
        $html = Markdown::toHtml('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_renders_underline_extension(): void
    {
        $html = Markdown::toHtml('++wichtig++');

        $this->assertStringContainsString('<u>wichtig</u>', $html);
    }

    public function test_strips_raw_html(): void
    {
        $html = Markdown::toHtml('<script>alert(1)</script>Text');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_empty_input_renders_to_empty_string(): void
    {
        $this->assertSame('', Markdown::toHtml(null));
        $this->assertSame('', Markdown::toHtml('   '));
    }
}
