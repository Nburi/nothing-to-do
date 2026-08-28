<?php

namespace Tests\Unit;

use App\Models\HelpArticle;
use Tests\TestCase;

class HelpArticleMarkdownTest extends TestCase
{
    public function test_renders_a_gfm_table(): void
    {
        $html = HelpArticle::renderMarkdown("| A | B |\n| --- | --- |\n| 1 | 2 |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>A</th>', $html);
        $this->assertStringContainsString('<td>1</td>', $html);
    }

    public function test_task_list_checkboxes_are_not_disabled(): void
    {
        $html = HelpArticle::renderMarkdown("- [ ] Schritt 1\n- [x] Schritt 2");

        $this->assertStringNotContainsString('disabled', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
    }

    public function test_underline_extension_still_works(): void
    {
        $html = HelpArticle::renderMarkdown('++wichtig++');

        $this->assertStringContainsString('<u>wichtig</u>', $html);
    }

    public function test_renders_headings_and_links(): void
    {
        $html = HelpArticle::renderMarkdown("# Titel\n\nSee [Google](https://google.com).");

        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<a href="https://google.com">Google</a>', $html);
    }

    public function test_raw_html_input_is_stripped(): void
    {
        $html = HelpArticle::renderMarkdown('<script>alert(1)</script>Text');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_empty_content_renders_to_empty_string(): void
    {
        $this->assertSame('', HelpArticle::renderMarkdown(null));
        $this->assertSame('', HelpArticle::renderMarkdown('   '));
    }

    public function test_preview_strips_markdown_syntax(): void
    {
        $article = new HelpArticle(['content' => "# Titel\n\n**Fett** Text.\n- [ ] Ein Punkt in einer Liste."]);

        $preview = $article->preview();

        $this->assertStringNotContainsString('#', $preview);
        $this->assertStringNotContainsString('**', $preview);
        $this->assertStringNotContainsString('[ ]', $preview);
    }

    public function test_preview_is_null_for_empty_content(): void
    {
        $article = new HelpArticle(['content' => null]);

        $this->assertNull($article->preview());
    }
}
